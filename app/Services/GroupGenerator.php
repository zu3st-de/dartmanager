<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\Game;

/**
 * ================================================================
 * GroupGenerator
 * ================================================================
 *
 * Verantwortlich für:
 *
 * - Gruppen erstellen
 * - Spieler gleichmäßig verteilen
 * - Round-Robin Spielplan erzeugen
 *
 * WICHTIG:
 *
 * - Diese Klasse erstellt NUR Struktur
 * - KEINE Auswertung / Tabelle (→ GroupTableCalculator)
 *
 * ================================================================
 */
class GroupGenerator
{
    /**
     * ============================================================
     * GRUPPEN + SPIELPLAN ERZEUGEN
     * ============================================================
     *
     * Ablauf:
     *
     * 1. Spieler laden & mischen
     * 2. Gruppen erstellen (A, B, C, ...)
     * 3. Spieler gleichmäßig verteilen
     * 4. Round-Robin Spiele pro Gruppe erzeugen
     */
    public function generate(Tournament $tournament, int $groupCount): void
    {
        /*
        |--------------------------------------------------------------------------
        | 1. Spieler laden & zufällig mischen
        |--------------------------------------------------------------------------
        */

        $players = $tournament->players()
            ->inRandomOrder()
            ->get()
            ->values();


        /*
        |--------------------------------------------------------------------------
        | 2. Gruppen erstellen (A, B, C, ...)
        |--------------------------------------------------------------------------
        */

        $groups = collect();

        for ($i = 0; $i < $groupCount; $i++) {

            $groups->push(
                $tournament->groups()->create([
                    'name' => chr(65 + $i)
                ])
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 3. Spieler gleichmäßig verteilen (Round-Robin Verteilung)
        |--------------------------------------------------------------------------
        |
        | Vorteil:
        | - gleich große Gruppen
        | - keine Clusterbildung
        |
        */

        foreach ($players as $index => $player) {

            $group = $groups[$index % $groupCount];

            $player->update([
                'group_id' => $group->id
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | 4. Für jede Gruppe Spielplan erzeugen
        |--------------------------------------------------------------------------
        */

        foreach ($groups as $group) {

            $groupPlayers = $group->players;

            // Sicherheitscheck
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

                    'round'         => $gameData['round'],
                    'position'      => $position++,

                    'best_of'       => $tournament->group_best_of ?? 3,
                ]);
            }
        }
    }


    /**
     * ============================================================
     * ROUND-ROBIN GENERATOR (CIRCLE METHOD)
     * ============================================================
     *
     * Eigenschaften:
     *
     * - Jeder spielt gegen jeden genau einmal
     * - Funktioniert für gerade & ungerade Spielerzahlen
     * - Gleichmäßige Verteilung der Gegner
     *
     * Rückgabe:
     *
     * Collection:
     * [
     *   [
     *     'round' => int,
     *     'player1_id' => int,
     *     'player2_id' => int,
     *   ],
     *   ...
     * ]
     */
    private function generateRoundRobin($players)
    {
        /*
        |--------------------------------------------------------------------------
        | 1. Spieler zufällig mischen
        |--------------------------------------------------------------------------
        */

        $players = $players->shuffle()->values()->all();

        $count = count($players);


        /*
        |--------------------------------------------------------------------------
        | 2. Freilos hinzufügen (bei ungerader Spielerzahl)
        |--------------------------------------------------------------------------
        */

        if ($count % 2 !== 0) {
            $players[] = null;
            $count++;
        }


        /*
        |--------------------------------------------------------------------------
        | 3. Grundwerte berechnen
        |--------------------------------------------------------------------------
        */

        $rounds = $count - 1;
        $half   = $count / 2;

        $schedule = [];


        /*
        |--------------------------------------------------------------------------
        | 4. Runden erzeugen
        |--------------------------------------------------------------------------
        */

        for ($round = 0; $round < $rounds; $round++) {

            for ($i = 0; $i < $half; $i++) {

                $p1 = $players[$i];
                $p2 = $players[$count - 1 - $i];

                /*
                |--------------------------------------------------------------------------
                | Freilos → überspringen
                |--------------------------------------------------------------------------
                */

                if (!$p1 || !$p2) {
                    continue;
                }


                /*
                |--------------------------------------------------------------------------
                | Seiten wechseln (Fairness)
                |--------------------------------------------------------------------------
                */

                if ($round % 2 === 0) {
                    $player1 = $p1;
                    $player2 = $p2;
                } else {
                    $player1 = $p2;
                    $player2 = $p1;
                }


                $schedule[] = [
                    'round' => $round + 1,
                    'player1_id' => $player1->id,
                    'player2_id' => $player2->id,
                ];
            }


            /*
            |--------------------------------------------------------------------------
            | Circle Rotation
            |--------------------------------------------------------------------------
            |
            | Spieler[0] bleibt fix
            |
            */

            $last = array_pop($players);
            array_splice($players, 1, 0, [$last]);
        }

        return collect($schedule);
    }
}
