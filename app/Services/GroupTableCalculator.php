<?php

namespace App\Services;

use App\Models\Group;

class GroupTableCalculator
{
    public function calculate(Group $group): array
    {
        $table = [];

        foreach ($group->players as $player) {

            $games = $group->games()
                ->where(function ($q) use ($player) {
                    $q->where('player1_id', $player->id)
                        ->orWhere('player2_id', $player->id);
                })
                ->whereNotNull('winner_id')
                ->get();

            $wins = 0;
            $losses = 0;
            $points = 0;
            $difference = 0;

            foreach ($games as $game) {

                $isWinner = $game->winner_id === $player->id;

                if ($isWinner) {
                    $wins++;
                    $points++; // 1 Punkt pro Sieg
                } else {
                    $losses++;
                }

                /*
                |--------------------------------------------------------------------------
                | Best Of 1
                |--------------------------------------------------------------------------
                */
                if ($game->best_of == 1) {

                    $rest = $game->winning_rest ?? 0;

                    if ($isWinner) {
                        $difference += $rest;
                    } else {
                        $difference -= $rest;
                    }

                    /*
                |--------------------------------------------------------------------------
                | Best Of > 1
                |--------------------------------------------------------------------------
                */
                } else {

                    if ($player->id == $game->player1_id) {
                        $difference += $game->player1_score - $game->player2_score;
                    } else {
                        $difference += $game->player2_score - $game->player1_score;
                    }
                }
            }

            $table[] = [
                'player' => $player,
                'played' => $wins + $losses,
                'wins' => $wins,
                'losses' => $losses,
                'points' => $points,
                'difference' => $difference,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Sortierung
        |--------------------------------------------------------------------------
        */
        usort($table, function ($a, $b) {

            if ($a['points'] !== $b['points']) {
                return $b['points'] <=> $a['points'];
            }

            return $b['difference'] <=> $a['difference'];
        });

        return $table;
    }
}
