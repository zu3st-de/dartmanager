<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\Game;

class GroupGenerator
{
    public function generate(Tournament $tournament, int $groupCount): void
    {
        // Spieler zufällig mischen
        $players = $tournament->players()->inRandomOrder()->get();

        // Gruppen erstellen
        $groups = collect();

        for ($i = 0; $i < $groupCount; $i++) {
            $groups->push(
                $tournament->groups()->create([
                    'name' => chr(65 + $i) // A, B, C, ...
                ])
            );
        }

        // Spieler gleichmäßig auf Gruppen verteilen
        foreach ($players as $index => $player) {
            $group = $groups[$index % $groupCount];
            $player->update(['group_id' => $group->id]);
        }

        // Für jede Gruppe Round-Robin erzeugen
        foreach ($groups as $group) {

            $groupPlayers = $group->players;

            if ($groupPlayers->count() < 2) {
                continue;
            }

            $games = $this->generateRoundRobin($groupPlayers);

            $position = 1;

            foreach ($games as $gameData) {

                Game::create([
                    'tournament_id' => $tournament->id,
                    'group_id'      => $group->id,
                    'player1_id'    => $gameData['player1_id'],
                    'player2_id'    => $gameData['player2_id'],
                    'is_group_match' => true,
                    'round'         => $gameData['round'],
                    'position'      => $position++,
                    'best_of'       => $tournament->group_best_of ?? 3,
                ]);
            }
        }
    }

    /**
     * Dynamischer Round-Robin Generator (Circle Method)
     */
    private function generateRoundRobin($players)
    {
        $players = $players->values()->all();
        $count = count($players);

        // Falls ungerade Spielerzahl → Freilos hinzufügen
        if ($count % 2 !== 0) {
            $players[] = null;
            $count++;
        }

        $rounds = $count - 1;
        $half = $count / 2;

        $schedule = [];

        for ($round = 0; $round < $rounds; $round++) {

            $roundGames = [];

            for ($i = 0; $i < $half; $i++) {

                $p1 = $players[$i];
                $p2 = $players[$count - 1 - $i];

                if ($p1 && $p2) {
                    $roundGames[] = [
                        'round' => $round + 1,
                        'player1_id' => $p1->id,
                        'player2_id' => $p2->id,
                    ];
                }
            }

            // Spiele innerhalb der Runde leicht mischen
            shuffle($roundGames);

            foreach ($roundGames as $game) {
                $schedule[] = $game;
            }

            // Rotation außer erstes Element (Circle Method)
            $last = array_pop($players);
            array_splice($players, 1, 0, [$last]);
        }

        return collect($schedule);
    }
}
