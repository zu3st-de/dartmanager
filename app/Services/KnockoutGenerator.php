<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\Game;

class KnockoutGenerator
{
    public function generate(Tournament $tournament, $players): void
    {
        Game::where('tournament_id', $tournament->id)
            ->where('round', '>', 0)
            ->delete();

        $players = $players->values();

        $position = 1;

        for ($i = 0; $i < $players->count(); $i += 2) {
            Game::create([
                'tournament_id' => $tournament->id,
                'player1_id' => $players[$i]->id,
                'player2_id' => $players[$i + 1]->id,
                'round' => 1,
                'position' => $position++,
                'best_of' => 3,
                'is_group_match' => 0,
            ]);
        }
    }
}
