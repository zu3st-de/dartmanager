<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\Game;
use Illuminate\Http\Request;
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

        $groupBestOf = $groupGames->value('best_of');

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

    /*
    |--------------------------------------------------------------------------
    | Spieler auslosen (Seed setzen)
    |--------------------------------------------------------------------------
    */

    public function draw(Tournament $tournament)
    {
        $this->authorizeTournament($tournament);

        if ($tournament->status !== 'draft') {
            abort(400);
        }

        $players = $tournament->players()
            ->inRandomOrder()
            ->get();

        foreach ($players as $index => $player) {
            $player->update([
                'seed' => $index + 1
            ]);
        }

        return back();
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
    | Gruppenphase Best-of aktualisieren
    |--------------------------------------------------------------------------
    */
    public function updateGroupBestOf(Request $request, Tournament $tournament)
    {
        $groupHasResults = $tournament->games()
            ->where('round', 0)
            ->where(function ($q) {
                $q->whereNotNull('winner_id');
            })
            ->exists();

        if ($groupHasResults) {
            return back()->with('error', 'Best Of kann nicht mehr geändert werden.');
        }
        $request->validate([
            'group_best_of' => 'required|in:1,3,5,7'
        ]);

        // Alle Gruppenspiele updaten (round = 0 bei dir)
        $tournament->games()
            ->where('round', 0)
            ->update([
                'best_of' => $request->group_best_of
            ]);

        return back();
    }
    /*
    |--------------------------------------------------------------------------
    | KO-Runde Best-of aktualisieren
    |--------------------------------------------------------------------------
    */
    public function updateRoundBestOf(
        Request $request,
        Tournament $tournament,
        int $round
    ) {
        $this->authorizeTournament($tournament);

        $request->validate([
            'best_of' => 'required|in:1,3,5,7,9',
            'is_third_place' => 'nullable|boolean'
        ]);

        $isThirdPlace = (bool) $request->input('is_third_place', false);

        $query = $tournament->games()
            ->where('round', $round)
            ->where('is_third_place', $isThirdPlace);

        // 🔥 WICHTIG: clone verwenden
        $hasResults = (clone $query)
            ->whereNotNull('winner_id')
            ->exists();

        if ($hasResults) {
            return back()->with('error', 'Best Of kann nicht mehr geändert werden.');
        }

        $query->update([
            'best_of' => $request->best_of
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

        $qualified = collect();

        foreach ($tournament->groups as $group) {

            $table = app(GroupTableCalculator::class)
                ->calculate($group);

            $topPlayers = collect($table)
                ->take($tournament->group_advance_count)
                ->pluck('player');

            $qualified = $qualified->merge($topPlayers);
        }

        if ($qualified->count() < 2) {
            abort(400);
        }

        app(KnockoutGenerator::class)
            ->generate($tournament, $qualified);

        $tournament->update([
            'status' => 'ko_running'
        ]);

        return redirect()
            ->route('tournaments.show', $tournament);
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
}
