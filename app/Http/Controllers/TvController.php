<?php

namespace App\Http\Controllers;

use App\Models\Tournament;

class TvController extends Controller
{
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
