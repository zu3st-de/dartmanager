<?php

namespace App\Services\Knockout;

use App\Models\Game;

class KnockoutProgressionService
{
    public function clearFromGame(Game $game): array
    {
        $reload = [];

        /*
        |--------------------------------------------------------------------------
        | Normales KO Folgespiel
        |--------------------------------------------------------------------------
        */

        $nextGame = Game::where('tournament_id', $game->tournament_id)
            ->where('round', $game->round + 1)
            ->where('position', ceil($game->position / 2))
            ->first();

        if ($nextGame) {

            if ($game->position % 2 === 1) {
                $nextGame->player1_id = null;
            } else {
                $nextGame->player2_id = null;
            }

            $reload[] = $nextGame->id;

            if ($nextGame->winner_id) {

                $nextGame->update([
                    'player1_score' => null,
                    'player2_score' => null,
                    'winner_id' => null,
                    'winning_rest' => null,
                ]);

                $reload = array_merge(
                    $reload,
                    $this->clearFromGame($nextGame)
                );
            }

            $nextGame->save();
        }

        /*
        |--------------------------------------------------------------------------
        | Spiel um Platz 3
        |--------------------------------------------------------------------------
        */

        $thirdPlace = Game::where('tournament_id', $game->tournament_id)
            ->where('is_third_place', true)
            ->first();

        if ($thirdPlace && $game->round == ($thirdPlace->round - 1)) {

            // Halbfinale 1 / 2
            if ($game->position % 2 === 1) {
                $thirdPlace->player1_id = null;
            } else {
                $thirdPlace->player2_id = null;
            }

            $reload[] = $thirdPlace->id;

            if ($thirdPlace->winner_id) {

                $thirdPlace->update([
                    'player1_score' => null,
                    'player2_score' => null,
                    'winner_id' => null,
                    'winning_rest' => null,
                ]);
            }

            $thirdPlace->save();
        }

        return $reload;
    }
}
