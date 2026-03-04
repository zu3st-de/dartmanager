<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Services\GroupTableCalculator;

class TvController extends Controller
{
    public function show(Tournament $tournament)
    {
        $tournament->load([
            'groups.players',
            'groups.games.player1',
            'groups.games.player2',
        ]);

        $groupData = $tournament->groups->map(function ($group) {

            $table = app(GroupTableCalculator::class)
                ->calculate($group);

            $lastGame = $group->games
                ->whereNotNull('winner_id')
                ->sortByDesc('updated_at')
                ->first();

            $nextGame = $group->games
                ->whereNull('winner_id')
                ->sortBy('id')
                ->first();

            return [
                'group' => $group,
                'table' => $table,
                'lastGame' => $lastGame,
                'nextGame' => $nextGame,
            ];
        });

        return view('tv.show', compact('tournament', 'groupData'));
    }
}
