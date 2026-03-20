<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\TvTournament;
use Illuminate\Http\Request;

/**
 * ================================================================
 * TvController
 * ================================================================
 *
 * Verantwortlich für:
 *
 * - Verwaltung des TV-Modus
 * - Auswahl der angezeigten Turniere
 * - Rotation (Anzeige mehrerer Turniere)
 * - Darstellung eines einzelnen Turniers im TV-Modus
 *
 * 🔒 WICHTIG:
 * Alle Methoden sind so abgesichert, dass ein User
 * NUR seine eigenen Turniere sehen/verwalten kann.
 *
 * ================================================================
 */

class TvController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | 📺 TV-Verwaltung anzeigen
    |--------------------------------------------------------------------------
    |
    | Zeigt eine Liste aller Turniere des aktuellen Users,
    | die für den TV-Modus ausgewählt werden können.
    |
    | 🔒 SECURITY:
    | Es werden NUR Turniere des eingeloggten Users geladen.
    |
    */
    public function manage()
    {
        $tournaments = auth()->user()
            ->tournaments()
            ->orderBy('name')
            ->get();

        // Bereits ausgewählte Turniere (TV-Programm)
        $selected = TvTournament::pluck('tournament_id')->toArray();
        $rotationTime = TvTournament::orderBy('position')->value('rotation_time') ?? 20;
        return view('admin.tv', compact('tournaments', 'selected', 'rotationTime'));
    }


    /*
    |--------------------------------------------------------------------------
    | 💾 TV-Auswahl speichern
    |--------------------------------------------------------------------------
    |
    | Speichert die Reihenfolge der Turniere für den TV-Modus.
    |
    | 🔒 SECURITY:
    | - Es werden nur Turniere gespeichert, die dem User gehören
    | - Fremde IDs werden ignoriert
    |
    */
    public function save(Request $request)
    {
        $userTournamentIds = auth()->user()
            ->tournaments()
            ->pluck('id')
            ->toArray();

        $rotationTime = (int) $request->rotation_time ?: 20;

        TvTournament::truncate();

        $position = 1;
        $created = false;

        foreach ($request->tournaments ?? [] as $id) {

            if (!in_array($id, $userTournamentIds)) {
                continue;
            }

            TvTournament::create([
                'tournament_id' => $id,
                'position' => $position,
                'rotation_time' => $rotationTime,
            ]);

            $position++;
            $created = true;
        }

        // 🔥 WICHTIG: Fallback wenn nichts ausgewählt wurde
        if (!$created) {
            TvTournament::create([
                'tournament_id' => null,
                'position' => 1,
                'rotation_time' => $rotationTime,
            ]);
        }

        return back()->with('success', 'TV Programm gespeichert');
    }


    /*
    |--------------------------------------------------------------------------
    | 🔄 TV-Rotation
    |--------------------------------------------------------------------------
    |
    | Lädt die ausgewählten Turniere in definierter Reihenfolge.
    |
    | 🔒 SECURITY:
    | - Nur Turniere des aktuellen Users werden angezeigt
    | - Schutz gegen manipulierte Datenbankeinträge
    |
    */
    public function rotation()
    {
        $tournaments = TvTournament::with('tournament')
            ->orderBy('position')
            ->get()
            ->pluck('tournament')
            ->filter(function ($tournament) {
                return $tournament && $tournament->user_id === auth()->id();
            })
            ->values();

        return view('tv.rotation', compact('tournaments'));
    }


    /*
    |--------------------------------------------------------------------------
    | 📺 Einzelnes Turnier im TV-Modus anzeigen
    |--------------------------------------------------------------------------
    |
    | Zeigt ein Turnier abhängig vom Status:
    |
    | - draft           → einfache Übersicht
    | - group_running   → Gruppen + Tabelle
    | - ko_running      → KO-Baum
    | - finished        → finaler Stand
    |
    | 🔒 SECURITY:
    | Zugriff nur für den Besitzer des Turniers
    |
    */
    public function show(Tournament $tournament)
    {
        // 🔒 OWNER CHECK (KRITISCH!)
        if ($tournament->user_id !== auth()->id()) {
            abort(403);
        }

        /*
        |--------------------------------------------------------------------------
        | 🔄 Relationen laden (Performance)
        |--------------------------------------------------------------------------
        */
        $tournament->load([
            'groups.players',
            'groups.games.player1',
            'groups.games.player2',
            'games.player1',
            'games.player2',
            'games.winner'
        ]);


        /*
        |--------------------------------------------------------------------------
        | 📝 Draft Phase
        |--------------------------------------------------------------------------
        */
        if ($tournament->status === 'draft') {
            return view('tv.draft', compact('tournament'));
        }


        /*
        |--------------------------------------------------------------------------
        | 👥 Gruppenphase
        |--------------------------------------------------------------------------
        */
        if ($tournament->status === 'group_running') {

            $groupData = [];

            foreach ($tournament->groups as $group) {

                // Tabelle berechnen
                $table = app(\App\Services\GroupTableCalculator::class)
                    ->calculate($group);

                // Letztes abgeschlossenes Spiel
                $lastGame = $group->games
                    ->whereNotNull('winner_id')
                    ->sortByDesc('updated_at')
                    ->first();

                // Aktuelles Spiel
                $currentGame = $group->games
                    ->whereNull('winner_id')
                    ->sortBy('id')
                    ->first();

                // Nächstes Spiel
                $nextGame = $group->games
                    ->whereNull('winner_id')
                    ->sortBy('id')
                    ->skip(1)
                    ->first();

                $groupData[] = [
                    'group' => $group,
                    'table' => $table,
                    'lastGame' => $lastGame,
                    'currentGame' => $currentGame,
                    'nextGame' => $nextGame,
                ];
            }

            return view('tv.show', compact('tournament', 'groupData'));
        }


        /*
        |--------------------------------------------------------------------------
        | 🏆 KO Phase
        |--------------------------------------------------------------------------
        */
        if (in_array($tournament->status, ['ko_running', 'finished'])) {

            $rounds = $tournament->games
                ->whereNull('group_id')
                ->sortBy([
                    ['round', 'asc'],
                    ['position', 'asc']
                ])
                ->groupBy('round');

            return view('tv.bracket', compact('tournament', 'rounds'));
        }


        /*
        |--------------------------------------------------------------------------
        | ❌ Fallback
        |--------------------------------------------------------------------------
        */
        abort(404);
    }
}
