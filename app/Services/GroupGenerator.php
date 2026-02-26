<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\Game;

class GroupGenerator
{
    public function generate(Tournament $tournament, int $groupCount): void
    {
        $players = $tournament->players()->inRandomOrder()->get();

        $groups = collect();

        for ($i = 0; $i < $groupCount; $i++) {
            $groups->push(
                $tournament->groups()->create([
                    'name' => chr(65 + $i)
                ])
            );
        }

        foreach ($players as $index => $player) {
            $group = $groups[$index % $groupCount];
            $player->update(['group_id' => $group->id]);
        }

        foreach ($groups as $group) {

            $groupPlayers = $group->players;

            foreach ($groupPlayers as $player1) {
                foreach ($groupPlayers as $player2) {

                    if ($player1->id < $player2->id) {

                        Game::create([
                            'tournament_id' => $tournament->id,
                            'group_id' => $group->id,
                            'player1_id' => $player1->id,
                            'player2_id' => $player2->id,
                            'is_group_match' => true,
                            'round' => 0,         // 🔥 wichtig
                            'position' => 0,      // 🔥 wichtig
                            'best_of' => 3,
                        ]);
                    }
                }
            }
        }
    }
}
