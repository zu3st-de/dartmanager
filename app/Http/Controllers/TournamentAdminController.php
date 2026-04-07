<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Tournament;
use App\Services\Group\GroupGenerator;
use App\Services\Group\GroupTableCalculator;
use App\Services\Knockout\KnockoutGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ================================================================
 * TournamentController
 * ================================================================
 *
 * Kern-Controller für Turnierverwaltung
 *
 * Verantwortlich für:
 *
 * - Turnierübersicht
 * - Turnier erstellen
 * - Turnier anzeigen
 * - Turnier starten
 * - Gruppenphase beenden
 * - KO Phase starten
 */
class TournamentAdminController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | TURNIER ÜBERSICHT
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $tournaments = auth()->user()
            ->tournaments()
            ->whereNotIn('status', ['archived'])
            ->latest()
            ->get();

        return view('tournaments.index', compact('tournaments'));
    }

    /*
    |--------------------------------------------------------------------------
    | TURNIER ERSTELLEN
    |--------------------------------------------------------------------------
    */

    public function create()
    {
        return view('tournaments.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'mode' => 'required|in:ko,group_ko',
            'group_count' => 'nullable|integer|min:1',
            'group_advance_count' => 'nullable|integer|min:1',
        ]);

        /*
        |--------------------------------------------------------------------------
        | group_ko Validierung
        |--------------------------------------------------------------------------
        */

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

            if (! $isPowerOfTwo) {

                return back()
                    ->withErrors([
                        'group_advance_count' => 'Die Gesamtzahl der KO-Teilnehmer muss eine 2er-Potenz sein.',
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
            ->with('success', 'Turnier erstellt');
    }

    /*
    |--------------------------------------------------------------------------
    | TURNIER ANZEIGEN
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

        $groupBestOf = $tournament->games()
            ->whereNotNull('group_id')
            ->pluck('best_of')
            ->unique()
            ->first() ?? 1;

        $groupHasResults = $tournament->games()
            ->whereNotNull('group_id')
            ->whereNotNull('winner_id')
            ->exists();

        return view(
            'tournaments.show',
            compact(
                'tournament',
                'groupBestOf',
                'groupHasResults',
                'koRounds'
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | TURNIER STARTEN
    |--------------------------------------------------------------------------
    */

    public function start(Tournament $tournament)
    {
        $this->authorizeTournament($tournament);

        if ($tournament->status !== 'draft') {
            return back()->with('error', 'Turnier bereits gestartet');
        }

        if ($tournament->players()->count() < 2) {
            return back()->with('error', 'Mindestens zwei Spieler erforderlich');
        }

        DB::transaction(function () use ($tournament) {

            /*
            |--------------------------------------------------------------------------
            | Gruppenphase + KO
            |--------------------------------------------------------------------------
            */

            if ($tournament->mode === 'group_ko') {

                app(GroupGenerator::class)
                    ->generate($tournament, $tournament->group_count);

                $size =
                    $tournament->group_count *
                    $tournament->group_advance_count;

                app(KnockoutGenerator::class)
                    ->generatePlaceholderBracket($tournament, $size);

                $tournament->update([
                    'status' => 'group_running',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Direktes KO
            |--------------------------------------------------------------------------
            */ else {

                $players = $tournament->players()->get()->values();

                app(KnockoutGenerator::class)
                    ->generateDirectBracket($tournament, $players);

                $tournament->update([
                    'status' => 'ko_running',
                ]);
            }
        });

        return redirect()
            ->route('tournaments.show', $tournament)
            ->with('success', 'Turnier gestartet');
    }

    /*
    |--------------------------------------------------------------------------
    | GRUPPENPHASE BEENDEN
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
                'Nicht alle Gruppenspiele abgeschlossen'
            );
        }

        return $this->startKo($tournament);
    }

    /*
    |--------------------------------------------------------------------------
    | KO STARTEN
    |--------------------------------------------------------------------------
    */

    public function startKo(Tournament $tournament)
    {
        $this->authorizeTournament($tournament);

        DB::transaction(function () use ($tournament) {

            $tables = [];

            foreach ($tournament->groups as $group) {

                $tables[$group->name] =
                    app(GroupTableCalculator::class)
                        ->calculate($group);
            }

            $games = $tournament->games()
                ->whereNull('group_id')
                ->get();

            foreach ($games as $game) {

                if ($game->player1_source) {
                    $game->player1_id =
                        $this->resolveGroupSource(
                            $game->player1_source,
                            $tables
                        );
                }

                if ($game->player2_source) {
                    $game->player2_id =
                        $this->resolveGroupSource(
                            $game->player2_source,
                            $tables
                        );
                }

                $game->save();
            }

            $tournament->update([
                'status' => 'ko_running',
            ]);
        });

        return redirect()
            ->route('tournaments.show', $tournament)
            ->with('success', 'KO gestartet');
    }

    /*
    |--------------------------------------------------------------------------
    | SECURITY
    |--------------------------------------------------------------------------
    */

    private function authorizeTournament(Tournament $tournament)
    {
        if ($tournament->user_id !== auth()->id()) {
            abort(403);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | GROUP SOURCE
    |--------------------------------------------------------------------------
    */

    private function resolveGroupSource(string $source, array $tables)
    {
        if (preg_match('/([A-Z])(\d+)/', $source, $match)) {

            $group = $match[1];
            $place = (int) $match[2] - 1;

            return $tables[$group][$place]['player']->id ?? null;
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Turnier wieder öffnen
    |--------------------------------------------------------------------------
    |
    | Setzt ein abgeschlossenes Turnier zurück auf "running"
    |
    */
    public function reopen(\App\Models\Tournament $tournament)
    {
        $tournament->update([
            'status' => 'ko_running',
            'winner_id' => null,
        ]);

        return redirect()
            ->route('tournaments.show', $tournament)
            ->with('success', 'Turnier wurde wieder geöffnet.');
    }

    /*
    |--------------------------------------------------------------------------
    | Turnier komplett zurücksetzen
    |--------------------------------------------------------------------------
    |
    | Löscht alle Ergebnisse (Gruppen + KO)
    |
    */
    public function reset(\App\Models\Tournament $tournament)
    {
        // Alle Spiele zurücksetzen
        \App\Models\Game::where('tournament_id', $tournament->id)
            ->update([
                'player1_score' => null,
                'player2_score' => null,
                'winner_id' => null,
            ]);

        // KO Slots leeren
        \App\Models\Game::where('tournament_id', $tournament->id)
            ->whereNull('group_id')
            ->update([
                'player1_id' => null,
                'player2_id' => null,
            ]);

        // Turnier Status zurücksetzen
        $tournament->update([
            'status' => 'group_running',
            'winner_id' => null,
        ]);

        return response()->json([
            'success' => true,
            'fullReload' => true,
        ]);
    }
}
