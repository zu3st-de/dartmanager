<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\Game;

class KnockoutGenerator
{
    public function generate(Tournament $tournament)
    {
        $players = $tournament->players()
            ->orderBy('seed')
            ->get()
            ->values();

        $count = $players->count();

        if ($count === 0 || ($count & ($count - 1)) !== 0) {
            throw new \Exception("Teilnehmerzahl muss 2er-Potenz sein.");
        }

        $round = 1;
        $position = 1;

        for ($i = 0; $i < $count; $i += 2) {
            Game::create([
                'tournament_id' => $tournament->id,
                'player1_id' => $players[$i]->id,
                'player2_id' => $players[$i + 1]->id,
                'round' => $round,
                'position' => $position,
            ]);

            $position++;
        }
    }
    public function generateFromCollection(Tournament $tournament, $players)
    {
        $players = collect($players)->values();

        $playerCount = $players->count();

        // Sicherstellen, dass es eine 2er-Potenz ist
        if (($playerCount & ($playerCount - 1)) !== 0) {
            throw new \Exception('KO-Teilnehmer müssen eine 2er-Potenz sein.');
        }

        $round = 1;
        $position = 1;

        for ($i = 0; $i < $playerCount; $i += 2) {

            \App\Models\Game::create([
                'tournament_id' => $tournament->id,
                'player1_id'    => $players[$i]->id,
                'player2_id'    => $players[$i + 1]->id,
                'round'         => $round,
                'position'      => $position++,
                'best_of'       => 3,
            ]);
        }
    }
}
