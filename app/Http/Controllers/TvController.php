<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\TvTournament;
use App\Services\Group\GroupTableCalculator;
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
 * 🔒 SECURITY:
 * Benutzer dürfen nur ihre eigenen Turniere sehen
 *
 */

class TvController extends Controller
{

    /*
    |--------------------------------------------------------------------------
    | 📺 TV-Verwaltung anzeigen
    |--------------------------------------------------------------------------
    |
    | Zeigt alle Turniere des Users zur Auswahl für TV
    |
    */

    public function manage()
    {
        // Alle Turniere des Users
        $tournaments = auth()->user()
            ->tournaments()
            ->where('status', '!=', 'archived')
            ->orderBy('name')
            ->get();


        // Bereits ausgewählte Turniere
        $selected = TvTournament::where('user_id', auth()->id())
            ->whereNotNull('tournament_id')
            ->pluck('tournament_id')
            ->toArray();


        // Rotationszeit
        $rotationTime = TvTournament::where('user_id', auth()->id())
            ->orderBy('position')
            ->value('rotation_time') ?? 20;


        return view('admin.tv', compact(
            'tournaments',
            'selected',
            'rotationTime'
        ));
    }


    /*
    |--------------------------------------------------------------------------
    | 💾 TV Auswahl speichern
    |--------------------------------------------------------------------------
    */

    public function save(Request $request)
    {
        $validated = $request->validate([
            'tournaments' => ['nullable', 'array'],
            'tournaments.*' => ['integer'],
            'rotation_time' => ['nullable', 'integer', 'min:5', 'max:300'],
        ]);

        // Nur eigene Turniere erlauben
        $userTournamentIds = auth()->user()
            ->tournaments()
            ->where('status', '!=', 'archived')
            ->pluck('id')
            ->toArray();


        // Rotationszeit
        $rotationTime = (int) ($validated['rotation_time'] ?? 20);


        // Alte Einträge löschen
        TvTournament::where('user_id', auth()->id())->delete();


        $position = 1;


        foreach ($validated['tournaments'] ?? [] as $id) {

            // Sicherheit: nur eigene Turniere
            if (!in_array($id, $userTournamentIds)) {
                continue;
            }

            TvTournament::create([
                'user_id' => auth()->id(),
                'tournament_id' => $id,
                'position' => $position,
                'rotation_time' => $rotationTime,
            ]);

            $position++;
        }


        return back()->with('success', 'TV Programm gespeichert');
    }


    /*
    |--------------------------------------------------------------------------
    | 🔄 TV Rotation
    |--------------------------------------------------------------------------
    */

    public function rotation()
    {
        $tournaments = TvTournament::with('tournament')
            ->where('user_id', auth()->id())
            ->orderBy('position')
            ->get()
            ->pluck('tournament')
            ->filter(function ($tournament) {

                // Nur eigene Turniere anzeigen
                return $tournament
                    && $tournament->user_id === auth()->id();
            })
            ->values();


        return view('tv.rotation', compact('tournaments'));
    }


    /*
    |--------------------------------------------------------------------------
    | 📺 Einzelnes Turnier anzeigen
    |--------------------------------------------------------------------------
    */

    public function show(Tournament $tournament)
    {
        /*
        |--------------------------------------------------------------------------
        | 🔒 Sicherheitsprüfung
        |--------------------------------------------------------------------------
        */

        if ($tournament->user_id !== auth()->id()) {
            abort(403);
        }


        /*
        |--------------------------------------------------------------------------
        | 🔄 Relationen laden
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
                $table = app(GroupTableCalculator::class)
                    ->calculate($group);


                // Letztes Spiel
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

            return view('tv.show', compact(
                'tournament',
                'groupData'
            ));
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

            return view('tv.bracket', compact(
                'tournament',
                'rounds'
            ));
        }


        /*
        |--------------------------------------------------------------------------
        | ❌ Fallback
        |--------------------------------------------------------------------------
        */

        abort(404);
    }
}
