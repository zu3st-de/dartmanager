<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\Player;
use App\Models\Group;
use App\Models\Game;

class PublicController extends Controller
{
    public function follow(Tournament $tournament)
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
    | Gruppendaten
    |---------------------------------------------------
    */

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
                'games' => $group->games,
                'lastGame' => $lastGame,
                'currentGame' => $currentGame,
                'nextGame' => $nextGame
            ];
        }


        /*
    |---------------------------------------------------
    | KO Phase
    |---------------------------------------------------
    */

        $koRounds = $tournament->games
            ->whereNull('group_id')
            ->sortBy([
                ['round', 'asc'],
                ['position', 'asc']
            ])
            ->groupBy('round');


        /*
    |---------------------------------------------------
    | Spiel um Platz 3
    |---------------------------------------------------
    */

        $thirdPlaceMatches = $tournament->games
            ->where('is_third_place', true);


        /*
    |---------------------------------------------------
    | Siegerpodest
    |---------------------------------------------------
    */

        $winner = null;
        $secondPlace = null;
        $thirdPlace = null;

        $final = $tournament->games
            ->whereNull('group_id')
            ->sortByDesc('round')
            ->first();

        if ($final && $final->winner) {

            $winner = $final->winner;

            $secondPlace =
                $final->player1_id === $final->winner_id
                ? $final->player2
                : $final->player1;
        }

        $thirdPlaceMatch = $thirdPlaceMatches->first();

        if ($thirdPlaceMatch && $thirdPlaceMatch->winner) {

            $thirdPlace = $thirdPlaceMatch->winner;
        }


        /*
    |---------------------------------------------------
    | Spieler (für Filter)
    |---------------------------------------------------
    */

        $players = $tournament->groups
            ->flatMap(fn($g) => $g->players)
            ->unique('id')
            ->values();


        return view('public.follow', compact(
            'tournament',
            'groupData',
            'players',
            'koRounds',
            'thirdPlaceMatches',
            'winner',
            'secondPlace',
            'thirdPlace'
        ));
    }
    public function followData(Tournament $tournament)
    {
        $tournament->load([
            'groups.players',
            'groups.games.player1',
            'groups.games.player2',
            'games.player1',
            'games.player2',
            'games.winner'
        ]);

        $groupData = [];

        foreach ($tournament->groups as $group) {

            $table = app(\App\Services\GroupTableCalculator::class)
                ->calculate($group);

            $groupData[] = [
                'group' => $group,
                'table' => $table,
                'games' => $group->games
            ];
        }

        $koRounds = $tournament->games
            ->whereNull('group_id')
            ->sortBy('round')
            ->groupBy('round');

        return response()->json([
            'groups' => $groupData,
            'ko' => $koRounds
        ]);
    }
}
