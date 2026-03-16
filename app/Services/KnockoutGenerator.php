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
    public function generatePlaceholderBracket(Tournament $tournament, int $size)
    {
        $rounds = (int) log($size, 2);

        $sources = $this->generateGroupSources($tournament);

        $position = 1;

        // Erste Runde
        for ($i = 0; $i < $size; $i += 2) {

            Game::create([
                'tournament_id' => $tournament->id,
                'group_id' => null,
                'player1_id' => null,
                'player2_id' => null,
                'round' => 1,
                'position' => $position++,
                'player1_source' => $sources[$i],
                'player2_source' => $sources[$i + 1],
                'best_of' => $tournament->ko_best_of ?? 3,
                'is_group_match' => 0,
                'is_third_place' => false,
            ]);
        }

        // Weitere Runden
        for ($round = 2; $round <= $rounds; $round++) {

            $gamesInRound = $size / pow(2, $round);

            for ($position = 1; $position <= $gamesInRound; $position++) {

                Game::create([
                    'tournament_id' => $tournament->id,
                    'group_id' => null,
                    'player1_id' => null,
                    'player2_id' => null,
                    'round' => $round,
                    'position' => $position,
                    'player1_source' => 'W' . ($position * 2 - 1),
                    'player2_source' => 'W' . ($position * 2),
                    'best_of' => $tournament->ko_best_of ?? 3,
                    'is_group_match' => 0,
                    'is_third_place' => false,
                ]);
            }
        }

        // Spiel um Platz 3
        if ($tournament->has_third_place) {

            Game::create([
                'tournament_id' => $tournament->id,
                'group_id' => null,
                'player1_id' => null,
                'player2_id' => null,
                'round' => $rounds,
                'position' => 99, // sicher außerhalb des Finals
                'player1_source' => 'L1',
                'player2_source' => 'L2',
                'best_of' => $tournament->ko_best_of ?? 3,
                'is_group_match' => 0,
                'is_third_place' => true,
            ]);
        }
    }
    private function generateGroupSources(Tournament $tournament)
    {
        $groups = $tournament->groups()
            ->orderBy('name')
            ->pluck('name')
            ->values();

        $advance = $tournament->group_advance_count;

        $seeds = [];

        // Seedliste erzeugen (A1 B1 C1 D1 A2 B2 C2 D2 ...)
        for ($place = 1; $place <= $advance; $place++) {

            foreach ($groups as $group) {
                $seeds[] = $group . $place;
            }
        }

        return $this->buildBracketOrder($seeds);
    }
    private function buildBracketOrder(array $seeds)
    {
        $size = count($seeds);

        $positions = $this->generateSeedPositions($size);

        $ordered = [];

        foreach ($positions as $pos) {
            $ordered[] = $seeds[$pos - 1];
        }

        return $ordered;
    }
    private function generateSeedPositions($size)
    {
        $positions = [1, 2];

        while (count($positions) < $size) {

            $new = [];

            $max = count($positions) * 2 + 1;

            foreach ($positions as $p) {

                $new[] = $p;
                $new[] = $max - $p;
            }

            $positions = $new;
        }

        return $positions;
    }
}
