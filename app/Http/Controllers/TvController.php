<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\TvTournament;
use Illuminate\Http\Request;

class TvController extends Controller
{
    public function manage()
    {
        $tournaments = Tournament::orderBy('name')->get();

        $selected = TvTournament::pluck('tournament_id')->toArray();

        return view('admin.tv', compact('tournaments', 'selected'));
    }
    public function save(Request $request)
    {
        TvTournament::truncate();

        $position = 1;

        foreach ($request->tournaments ?? [] as $id) {

            TvTournament::create([
                'tournament_id' => $id,
                'position' => $position
            ]);

            $position++;
        }

        return redirect()->back()->with('success', 'TV Programm gespeichert');
    }
    public function rotation()
    {
        $tournaments = TvTournament::with('tournament')
            ->orderBy('position')
            ->get()
            ->pluck('tournament');

        return view('tv.rotation', compact('tournaments'));
    }
    public function show(Tournament $tournament)
    {
        $tournament->load([
            'groups.players',
            'groups.games.player1',
            'groups.games.player2',
            'games.player1',
            'games.player2',
            'games.winner'
        ]);
        /*
        |---------------------------------------------------
        | Draft Phase (Turnier noch nicht gestartet)
        |---------------------------------------------------
        */

        if ($tournament->status === 'draft') {

            return view('tv.draft', compact('tournament'));
        }

        /*
        |---------------------------------------------------
        | Gruppenphase
        |---------------------------------------------------
        */

        if ($tournament->status === 'group_running') {

            $groupData = [];

            foreach ($tournament->groups as $group) {

                $table = app(\App\Services\GroupTableCalculator::class)
                    ->calculate($group);

                $lastGame = $group->games
                    ->whereNotNull('winner_id')
                    ->sortByDesc('updated_at')
                    ->first();

                $currentGame = $group->games
                    ->whereNull('winner_id')
                    ->sortBy('id')
                    ->first();

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
        |---------------------------------------------------
        | KO Phase
        |---------------------------------------------------
        */ elseif (in_array($tournament->status, ['ko_running', 'finished'])) {

            $rounds = $tournament->games
                ->whereNull('group_id')
                ->sortBy([
                    ['round', 'asc'],
                    ['position', 'asc']
                ])
                ->groupBy('round');

            return view('tv.bracket', compact('tournament', 'rounds'));
        }


        abort(404);
    }
}
