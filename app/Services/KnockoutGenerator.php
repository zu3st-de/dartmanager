<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\Game;

class KnockoutGenerator
{
    public function generate(Tournament $tournament, $qualifiedPlayers): void
    {
        // Alte KO-Spiele löschen
        Game::where('tournament_id', $tournament->id)
            ->whereNull('group_id')
            ->delete();

        $qualifiedPlayers = collect($qualifiedPlayers)->values();

        $playerCount = $qualifiedPlayers->count();

        if ($playerCount < 2) {
            return;
        }

        // 🔁 Überkreuz-Paarungen erzeugen
        $firstRoundMatches = $this->generateCrossMatches($qualifiedPlayers);

        // 🏁 Erste Runde erzeugen
        $round = 1;
        $position = 1;

        foreach ($firstRoundMatches as $match) {

            Game::create([
                'tournament_id' => $tournament->id,
                'group_id'      => null,
                'player1_id'    => $match['player1_id'],
                'player2_id'    => $match['player2_id'],
                'round'         => $round,
                'position'      => $position++,
                'best_of'       => $tournament->ko_best_of ?? 3,
                'is_group_match' => 0,
            ]);
        }

        // 🌳 Restlichen Baum vorbereiten
        $totalRounds = (int) round(log($playerCount, 2));

        $tournament->update([
            'ko_rounds' => $totalRounds
        ]);
        for ($r = 2; $r <= $totalRounds; $r++) {

            $matchesInRound = $playerCount / pow(2, $r);

            for ($i = 1; $i <= $matchesInRound; $i++) {

                Game::create([
                    'tournament_id' => $tournament->id,
                    'group_id'      => null,
                    'player1_id'    => null,
                    'player2_id'    => null,
                    'round'         => $r,
                    'position'      => $i,
                    'best_of'       => $tournament->ko_best_of ?? 3,
                    'is_group_match' => 0,
                    'is_third_place' => false,
                ]);
            }
        }
        if ($tournament->has_third_place) {

            Game::create([
                'tournament_id' => $tournament->id,
                'group_id'      => null,
                'player1_id'    => null,
                'player2_id'    => null,
                'round'         => $totalRounds, // gleiche Runde wie Finale
                'position'      => 2,
                'best_of'       => $tournament->ko_best_of ?? 3,
                'is_group_match' => 0,
                'is_third_place' => true,
            ]);
        }
    }

    /**
     * Überkreuz zwischen Gruppen
     */
    private function generateCrossMatches($players)
    {
        $players = collect($players)->values();

        $matches = collect();

        $total = $players->count();

        for ($i = 0; $i < $total / 2; $i++) {

            $matches->push([
                'player1_id' => $players[$i]->id,
                'player2_id' => $players[$total - 1 - $i]->id,
            ]);
        }

        return $matches;
    }
}
