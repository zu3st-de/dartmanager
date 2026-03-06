<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\KnockoutGenerator;
use App\Services\TournamentEngine;
use App\Services\GroupGenerator;
use App\Services\GroupTableCalculator;


class TournamentController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Übersicht
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $tournaments = auth()->user()
            ->tournaments()
            ->latest()
            ->get();

        return view('tournaments.index', compact('tournaments'));
    }

    public function create()
    {
        return view('tournaments.create');
    }

    /*
    |--------------------------------------------------------------------------
    | Turnier erstellen
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'mode' => 'required|in:ko,group_ko',
            'group_count' => 'nullable|integer|min:1',
            'group_advance_count' => 'nullable|integer|min:1',
        ]);

        if ($validated['mode'] === 'group_ko') {

            $request->validate([
                'group_count' => 'required|integer|min:1',
                'group_advance_count' => 'required|integer|min:1',
            ]);

            $qualifiedCount =
                $validated['group_count'] *
                $validated['group_advance_count'];

            $isPowerOfTwo =
                $qualifiedCount > 0 &&
                ($qualifiedCount & ($qualifiedCount - 1)) === 0;

            if (!$isPowerOfTwo) {
                return back()
                    ->withErrors([
                        'group_advance_count' =>
                        'Die Gesamtzahl der KO-Teilnehmer muss eine 2er-Potenz sein.'
                    ])
                    ->withInput();
            }
        }

        auth()->user()->tournaments()->create([
            'name' => $validated['name'],
            'mode' => $validated['mode'],
            'group_count' => $validated['group_count'] ?? null,
            'group_advance_count' => $validated['group_advance_count'] ?? null,
            'has_lucky_loser' => $request->has('has_lucky_loser'),
            'has_third_place' => $request->has('has_third_place'),
            'status' => 'draft',
        ]);

        return redirect()
            ->route('tournaments.index')
            ->with('success', 'Turnier erfolgreich erstellt.');
    }

    /*
    |--------------------------------------------------------------------------
    | Detailansicht
    |--------------------------------------------------------------------------
    */

    public function show(Tournament $tournament)
    {
        $this->authorizeTournament($tournament);

        $tournament->load([
            'players',
            'groups.players',
            'groups.games.player1',
            'groups.games.player2',
            'games.player1',
            'games.player2',
        ]);

        $koRounds = $tournament->games()
            ->where('round', '>', 0)
            ->where('is_third_place', false)
            ->select('round')
            ->distinct()
            ->orderBy('round')
            ->pluck('round');

        $groupGames = $tournament->games()
            ->where('round', 0);

        $groupBestOf = $tournament->games()
            ->whereNotNull('group_id')
            ->pluck('best_of')
            ->unique()
            ->first() ?? 3;

        $groupHasResults = $groupGames
            ->where(function ($q) {
                $q->whereNotNull('winner_id');
            })
            ->exists();

        return view('tournaments.show', compact('tournament', 'groupBestOf', 'groupHasResults', 'koRounds'));
    }

    /*
    |--------------------------------------------------------------------------
    | Spieler hinzufügen
    |--------------------------------------------------------------------------
    */

    public function addPlayer(Request $request, Tournament $tournament)
    {
        $this->authorizeTournament($tournament);

        if ($tournament->status !== 'draft') {
            abort(400);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $player = $tournament->players()->create([
            'name' => $validated['name'],
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'id' => $player->id,
                'name' => $player->name,
            ]);
        }

        return back();
    }
    public function bulkPlayers(Request $request, Tournament $tournament)
    {
        if ($tournament->user_id !== auth()->id()) {
            abort(403);
        }

        if ($tournament->status !== 'draft') {
            return back()->with('error', 'Spieler können nur im Draft hinzugefügt werden.');
        }

        $request->validate([
            'bulk_players' => 'required|string'
        ]);

        $lines = preg_split('/\r\n|\r|\n/', $request->bulk_players);

        $count = 0;

        foreach ($lines as $line) {

            $name = trim($line);

            if ($name === '') {
                continue;
            }
            if ($tournament->players()->where('name', $name)->exists()) {
                continue;
            }
            // Falls aus Excel mit Tabs kopiert → nur erste Spalte
            $name = explode("\t", $name)[0];

            $tournament->players()->create([
                'name' => $name
            ]);

            $count++;
        }

        return back()->with('success', "$count Spieler importiert.");
    }
    /*
    |--------------------------------------------------------------------------
    | Spieler auslosen (Seed setzen)
    |--------------------------------------------------------------------------
    */
    public function draw(Tournament $tournament)
    {
        if ($tournament->status !== 'draft') {
            return back()->with('error', 'Auslosung nur im Entwurfsmodus möglich.');
        }
        DB::transaction(function () use ($tournament) {

            $players = $tournament->players()
                ->inRandomOrder()
                ->lockForUpdate()
                ->get();

            foreach ($players as $index => $player) {
                $player->update([
                    'seed' => $index + 1
                ]);
            }
        });

        return back()->with('success', 'Auslosung durchgeführt.');
    }

    /*
    |--------------------------------------------------------------------------
    | Turnier starten
    |--------------------------------------------------------------------------
    */

    public function start(Tournament $tournament)
    {
        $this->authorizeTournament($tournament);

        if ($tournament->mode === 'group_ko') {

            if (
                !$tournament->group_count ||
                !$tournament->group_advance_count
            ) {
                abort(400);
            }

            app(GroupGenerator::class)
                ->generate($tournament, $tournament->group_count);

            $tournament->update([
                'status' => 'group_running'
            ]);

            return redirect()
                ->route('tournaments.show', $tournament);
        }

        if ($tournament->mode === 'ko') {

            $players = $tournament->players()
                ->orderBy('seed')
                ->get();

            if ($players->count() < 2) {
                abort(400);
            }

            app(KnockoutGenerator::class)
                ->generate($tournament, $players);

            $tournament->update([
                'status' => 'ko_running'
            ]);
        }

        return redirect()
            ->route('tournaments.show', $tournament);
    }

    /*
    |--------------------------------------------------------------------------
    | Score aktualisieren
    |--------------------------------------------------------------------------
    */

    public function updateScore(Request $request, Game $game)
    {
        $tournament = $game->tournament;
        $this->authorizeTournament($tournament);

        // Spiel bereits entschieden?
        if ($game->winner_id) {

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Spiel bereits entschieden'
                ]);
            }

            return back();
        }

        // Grundvalidierung
        $validated = $request->validate([
            'player1_score' => 'required|integer|min:0',
            'player2_score' => 'required|integer|min:0',
            'winning_rest'  => 'nullable|integer|min:0|max:501',
        ]);

        $player1Score = (int) $validated['player1_score'];
        $player2Score = (int) $validated['player2_score'];
        $winningRest  = $validated['winning_rest'] ?? null;

        /*
    |--------------------------------------------------------------------------
    | Zentrale Best-Of Validierung (im Model!)
    |--------------------------------------------------------------------------
    */

        $game->validateResult(
            $player1Score,
            $player2Score,
            $winningRest
        );

        /*
    |--------------------------------------------------------------------------
    | Scores speichern
    |--------------------------------------------------------------------------
    */

        $game->update([
            'player1_score' => $player1Score,
            'player2_score' => $player2Score,
            'winning_rest'  => ($game->best_of == 1 && $game->group_id !== null)
                ? $winningRest
                : null,
        ]);

        /*
    |--------------------------------------------------------------------------
    | Gewinner ermitteln
    |--------------------------------------------------------------------------
    */

        $firstTo = (int) ceil($game->best_of / 2);
        $winnerId = null;

        if ($player1Score >= $firstTo) {
            $winnerId = $game->player1_id;
        } elseif ($player2Score >= $firstTo) {
            $winnerId = $game->player2_id;
        }

        /*
    |--------------------------------------------------------------------------
    | KO-Engine ausführen
    |--------------------------------------------------------------------------
    */

        if ($winnerId) {
            app(TournamentEngine::class)
                ->handleWin($game, $winnerId);
        }

        $game->refresh();

        /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'game_id' => $game->id,
                'winner_id' => $game->winner_id,
                'player1_score' => $game->player1_score,
                'player2_score' => $game->player2_score,
            ]);
        }

        return back();
    }
    /*
    |--------------------------------------------------------------------------
    | KO-Runde Best-of aktualisieren
    |--------------------------------------------------------------------------
    */
    public function updateGroupBestOf(Request $request, Tournament $tournament)
    {
        $groupHasResults = $tournament->games()
            ->whereNotNull('group_id')
            ->whereNotNull('winner_id')
            ->exists();

        if ($groupHasResults) {
            return back()->with('error', 'Best Of kann nicht mehr geändert werden.');
        }

        $request->validate([
            'group_best_of' => 'required|in:1,3,5,7'
        ]);

        $tournament->games()
            ->whereNotNull('group_id')
            ->update([
                'best_of' => $request->group_best_of
            ]);

        return back();
    }
    /*
    |--------------------------------------------------------------------------
    | Gruppenphase abschließen
    |--------------------------------------------------------------------------
    */

    public function finishGroups(Tournament $tournament)
    {
        $this->authorizeTournament($tournament);

        $unfinished = Game::where('tournament_id', $tournament->id)
            ->whereNotNull('group_id')
            ->whereNull('winner_id')
            ->exists();

        if ($unfinished) {
            return back()->with(
                'error',
                'Nicht alle Gruppenspiele sind abgeschlossen.'
            );
        }

        return $this->startKo($tournament);
    }

    /*
    |--------------------------------------------------------------------------
    | KO aus Gruppen generieren
    |--------------------------------------------------------------------------
    */
    public function startKo(Tournament $tournament)
    {
        $this->authorizeTournament($tournament);

        if ($tournament->status !== 'group_running') {
            abort(400);
        }

        $qualifiedPlayers = $this->getKoQualifiedPlayers($tournament);

        if ($qualifiedPlayers->count() < 2) {
            abort(400);
        }

        app(KnockoutGenerator::class)
            ->generate($tournament, $qualifiedPlayers);

        $tournament->update([
            'status' => 'ko_running'
        ]);

        return redirect()
            ->route('tournaments.show', $tournament);
    }
    private function getKoQualifiedPlayers(Tournament $tournament)
    {
        $groups = $tournament->groups()
            ->orderBy('name')
            ->get();

        $advance = $tournament->group_advance_count;

        $qualified = collect();
        $remaining = collect();

        foreach ($groups as $group) {

            $table = app(GroupTableCalculator::class)
                ->calculate($group);

            foreach ($table as $index => $row) {

                if ($index < $advance) {
                    $qualified->push($row); // kompletter Tabellen-Datensatz
                } else {
                    $remaining->push($row);
                }
            }
        }

        $total = $qualified->count();
        $targetSize = 2 ** ceil(log($total, 2));

        if ($total < $targetSize) {

            $needed = $targetSize - $total;

            $bestRemaining = $remaining
                ->sortByDesc('points')
                ->sortByDesc('difference')
                ->take($needed);

            $qualified = $qualified->merge($bestRemaining);
        }

        return $qualified
            ->pluck('player')
            ->values();
    }
    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    */

    private function authorizeTournament(Tournament $tournament)
    {
        if ($tournament->user_id !== auth()->id()) {
            abort(403);
        }
    }

    public function reset(Request $request, Tournament $tournament)
    {
        // Nur Owner
        if ($tournament->user_id !== auth()->id()) {
            abort(403);
        }

        // Name muss exakt passen
        $request->validate([
            'confirm_name' => ['required', 'in:' . $tournament->name],
        ]);

        DB::transaction(function () use ($tournament) {

            // Alle Spiele löschen
            $tournament->games()->delete();

            // Optional: Gruppen löschen
            $tournament->groups()->delete();

            // Status zurücksetzen
            $tournament->update([
                'status' => 'draft'
            ]);
        });

        return redirect()
            ->route('tournaments.show', $tournament)
            ->with('success', 'Turnier wurde zurückgesetzt.');
    }

    public function resetGame(Game $game)
    {
        $tournament = $game->tournament;

        if ($tournament->user_id !== auth()->id()) {
            abort(403);
        }

        DB::transaction(function () use ($game, $tournament) {

            $oldWinnerId = $game->winner_id;

            // 1️⃣ Dieses Spiel zurücksetzen
            $game->update([
                'player1_score' => null,
                'player2_score' => null,
                'winner_id'     => null,
                'winning_rest'  => null,
            ]);

            // 2️⃣ Wenn KO-Spiel → Folge-Spiel berechnen
            if ($game->group_id === null && $game->round !== null) {

                $nextRound = $game->round + 1;
                $nextPosition = (int) ceil($game->position / 2);

                $nextGame = Game::where('tournament_id', $tournament->id)
                    ->where('round', $nextRound)
                    ->where('position', $nextPosition)
                    ->first();

                if ($nextGame && $oldWinnerId) {

                    // Entferne Gewinner aus nächstem Spiel
                    if ($nextGame->player1_id === $oldWinnerId) {
                        $nextGame->player1_id = null;
                    }

                    if ($nextGame->player2_id === $oldWinnerId) {
                        $nextGame->player2_id = null;
                    }

                    // Falls dadurch unvollständig → Ergebnis löschen
                    if (!$nextGame->player1_id || !$nextGame->player2_id) {
                        $nextGame->player1_score = null;
                        $nextGame->player2_score = null;
                        $nextGame->winner_id     = null;
                        $nextGame->winning_rest  = null;
                    }

                    $nextGame->save();
                }
            }
        });

        return back()->with('success', 'Spiel erfolgreich zurückgesetzt.');
    }

    public function reopen(Tournament $tournament)
    {
        if ($tournament->user_id !== auth()->id()) {
            abort(403);
        }

        if ($tournament->status !== 'finished') {
            return back()->with('error', 'Nur abgeschlossene Turniere können wieder geöffnet werden.');
        }

        // Falls KO existiert → zurück auf KO-Phase
        if ($tournament->games()->whereNull('group_id')->exists()) {
            $newStatus = 'ko_running';
        } else {
            $newStatus = 'group_running';
        }

        $tournament->update([
            'status' => $newStatus,
        ]);
        $tournament->update([
            'winner_id' => null
        ]);

        return back()->with('success', 'Turnier wurde wieder geöffnet.');
    }

    public function resetKo(Tournament $tournament)
    {
        $this->authorizeTournament($tournament);

        if ($tournament->status !== 'ko_running') {
            return back()->with('error', 'KO-Phase läuft nicht.');
        }

        DB::transaction(function () use ($tournament) {

            // 🔥 Nur KO-Spiele löschen
            $tournament->games()
                ->whereNull('group_id')
                ->delete();

            // Gewinner zurücksetzen
            $tournament->update([
                'status' => 'group_running',
                'winner_id' => null
            ]);
        });

        return back()->with('success', 'KO-Phase wurde zurückgesetzt.');
    }
}
