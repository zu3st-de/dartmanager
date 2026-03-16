<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\Game;

/**
 * KnockoutGenerator
 *
 * Diese Klasse ist für alles verantwortlich,
 * was mit dem KO-Baum zu tun hat:
 *
 * - KO Baum erzeugen
 * - Bracket mit Quellen erstellen (A1 vs B2 etc.)
 * - Spieler später in den Baum einsetzen
 */
class KnockoutGenerator
{

    /**
     * ------------------------------------------------------------
     * KO Bracket Platzhalter erzeugen
     * ------------------------------------------------------------
     *
     * Wird beim Start eines Group+KO Turniers aufgerufen.
     *
     * Beispiel für 8 Spieler:
     *
     * Runde 1:
     * A1 vs B2
     * C1 vs D2
     * B1 vs A2
     * D1 vs C2
     *
     * Runde 2:
     * W1 vs W2
     * W3 vs W4
     *
     * Runde 3:
     * W1 vs W2
     */
    public function generatePlaceholderBracket(Tournament $tournament, int $size)
    {

        $rounds = (int) log($size, 2);

        $sources = $this->generateGroupSources($tournament);

        $position = 1;

        /**
         * ------------------------------------------------------------
         * Erste KO Runde erstellen
         * ------------------------------------------------------------
         */

        for ($i = 0; $i < $size; $i += 2) {

            Game::create([
                'tournament_id' => $tournament->id,
                'group_id' => null,

                'player1_id' => null,
                'player2_id' => null,

                'player1_source' => $sources[$i],
                'player2_source' => $sources[$i + 1],

                'round' => 1,
                'position' => $position++,

                'best_of' => $tournament->ko_best_of ?? 3,

                'is_group_match' => false,
                'is_third_place' => false,
            ]);
        }


        /**
         * ------------------------------------------------------------
         * Weitere KO Runden vorbereiten
         * ------------------------------------------------------------
         */

        for ($round = 2; $round <= $rounds; $round++) {

            $gamesInRound = $size / pow(2, $round);

            for ($position = 1; $position <= $gamesInRound; $position++) {

                Game::create([
                    'tournament_id' => $tournament->id,
                    'group_id' => null,

                    'player1_id' => null,
                    'player2_id' => null,

                    'player1_source' => 'W' . ($position * 2 - 1),
                    'player2_source' => 'W' . ($position * 2),

                    'round' => $round,
                    'position' => $position,

                    'best_of' => $tournament->ko_best_of ?? 3,

                    'is_group_match' => false,
                    'is_third_place' => false,
                ]);
            }
        }


        /**
         * ------------------------------------------------------------
         * Spiel um Platz 3 erzeugen
         * ------------------------------------------------------------
         */

        if ($tournament->has_third_place) {

            Game::create([
                'tournament_id' => $tournament->id,
                'group_id' => null,

                'player1_id' => null,
                'player2_id' => null,

                'player1_source' => 'L1',
                'player2_source' => 'L2',

                'round' => $rounds,
                'position' => 99,

                'best_of' => $tournament->ko_best_of ?? 3,

                'is_group_match' => false,
                'is_third_place' => true,
            ]);
        }
    }



    /**
     * ------------------------------------------------------------
     * Spieler in den vorbereiteten KO Baum einsetzen
     * ------------------------------------------------------------
     *
     * Wird nach Abschluss der Gruppenphase aufgerufen.
     *
     * Beispiel:
     * Spielerliste:
     *
     * A1
     * B1
     * C1
     * D1
     * A2
     * B2
     * C2
     * D2
     *
     * Diese werden automatisch korrekt in die
     * erste KO Runde eingesetzt.
     */
    public function fillBracketPlayers(Tournament $tournament, $players): void
    {

        $players = collect($players)->values();

        $games = Game::where('tournament_id', $tournament->id)
            ->whereNull('group_id')
            ->where('round', 1)
            ->orderBy('position')
            ->get();


        foreach ($games as $index => $game) {

            $player1 = $players[$index * 2] ?? null;
            $player2 = $players[$index * 2 + 1] ?? null;

            $game->update([
                'player1_id' => $player1?->id,
                'player2_id' => $player2?->id
            ]);
        }
    }



    /**
     * ------------------------------------------------------------
     * Gruppen-Seeds generieren
     * ------------------------------------------------------------
     *
     * Beispiel:
     *
     * Gruppe A,B,C,D
     * Advance = 2
     *
     * Seeds:
     *
     * A1
     * B1
     * C1
     * D1
     * A2
     * B2
     * C2
     * D2
     */
    private function generateGroupSources(Tournament $tournament)
    {

        $groups = $tournament->groups()
            ->orderBy('name')
            ->pluck('name')
            ->values();

        $advance = $tournament->group_advance_count;

        $seeds = [];

        for ($place = 1; $place <= $advance; $place++) {

            foreach ($groups as $group) {
                $seeds[] = $group . $place;
            }
        }

        return $this->buildBracketOrder($seeds);
    }



    /**
     * ------------------------------------------------------------
     * Turnier-Seeding erzeugen
     * ------------------------------------------------------------
     *
     * Beispiel für 8 Seeds:
     *
     * 1 vs 8
     * 4 vs 5
     * 2 vs 7
     * 3 vs 6
     */
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



    /**
     * ------------------------------------------------------------
     * Seed Positionen berechnen
     * ------------------------------------------------------------
     *
     * Beispiel für 8 Spieler:
     *
     * 1
     * 8
     * 4
     * 5
     * 2
     * 7
     * 3
     * 6
     */
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
