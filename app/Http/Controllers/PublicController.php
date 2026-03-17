<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Services\GroupTableCalculator;

class PublicController extends Controller
{
    public function follow(Tournament $tournament)
    {
        $tournament->load($this->relations());

        $groupData = $this->buildGroupData($tournament);

        $koRounds = $this->buildKoRounds($tournament);
        $thirdPlaceMatches = $this->getThirdPlaceMatches($tournament);

        [$winner, $secondPlace, $thirdPlace] =
            $this->resolvePodium($tournament, $thirdPlaceMatches);

        $players = $this->getPlayers($tournament);

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
        $tournament->load($this->relations());

        return response()->json([
            'groups' => $this->buildGroupData($tournament),
            'ko' => $this->buildKoRounds($tournament),
            'tournament_status' => $tournament->status,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    private function relations(): array
    {
        return [
            'groups.players',
            'groups.games.player1',
            'groups.games.player2',
            'games.player1',
            'games.player2',
            'games.winner'
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | GROUP DATA
    |--------------------------------------------------------------------------
    */

    private function buildGroupData(Tournament $tournament): array
    {
        $groupData = [];

        foreach ($tournament->groups as $group) {

            $games = $group->games;

            $groupData[] = [
                'group' => $group,
                'table' => app(GroupTableCalculator::class)->calculate($group),
                'games' => $games,
                'lastGame' => $games->whereNotNull('winner_id')->sortByDesc('updated_at')->first(),
                'currentGame' => $games->whereNull('winner_id')->sortBy('id')->first(),
                'nextGame' => $games->whereNull('winner_id')->sortBy('id')->skip(1)->first(),
            ];
        }

        return $groupData;
    }


    /*
    |--------------------------------------------------------------------------
    | KO ROUNDS
    |--------------------------------------------------------------------------
    */

    private function buildKoRounds(Tournament $tournament)
    {
        return $tournament->games
            ->whereNull('group_id')
            ->where('is_third_place', false)
            ->sortBy([
                ['round', 'asc'],
                ['position', 'asc']
            ])
            ->groupBy('round');
    }


    /*
    |--------------------------------------------------------------------------
    | THIRD PLACE
    |--------------------------------------------------------------------------
    */

    private function getThirdPlaceMatches(Tournament $tournament)
    {
        return $tournament->games
            ->where('is_third_place', true);
    }


    /*
    |--------------------------------------------------------------------------
    | PODIUM
    |--------------------------------------------------------------------------
    */

    private function resolvePodium(Tournament $tournament, $thirdPlaceMatches): array
    {
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

        return [$winner, $secondPlace, $thirdPlace];
    }


    /*
    |--------------------------------------------------------------------------
    | PLAYERS
    |--------------------------------------------------------------------------
    */

    private function getPlayers(Tournament $tournament)
    {
        return $tournament->groups
            ->flatMap(fn($g) => $g->players)
            ->unique('id')
            ->values();
    }
}
