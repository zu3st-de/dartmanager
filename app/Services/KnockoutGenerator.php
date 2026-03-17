<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\Game;

/**
 * ================================================================
 * KnockoutGenerator
 * ================================================================
 *
 * Verantwortlich für den kompletten Aufbau des KO-Baums.
 *
 * Aufgaben:
 *
 * - KO-Bracket als Platzhalter erzeugen (ohne Spieler)
 * - Gruppenquellen korrekt in die erste Runde setzen (A1, B2, ...)
 * - Turnier-Seeding (1 vs 8, 4 vs 5, ...)
 * - Spieler später in Runde 1 einsetzen (optional)
 *
 * WICHTIG:
 *
 * - Diese Klasse erstellt nur die STRUKTUR
 * - KEINE Spiellogik (→ übernimmt TournamentEngine)
 *
 * ================================================================
 */
class KnockoutGenerator
{

    /**
     * ============================================================
     * PLACEHOLDER-BRACKET ERZEUGEN
     * ============================================================
     *
     * Wird beim Turnierstart (group_ko) aufgerufen.
     *
     * Erstellt:
     *
     * - komplette KO-Struktur
     * - inkl. aller Runden
     * - inkl. Quellen (A1, B2, W1, ...)
     *
     * KEINE Spieler werden gesetzt!
     *
     * Beispiel (8 Spieler):
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
    public function generatePlaceholderBracket(Tournament $tournament, int $size): void
    {
        $rounds = (int) log($size, 2);

        $sources = $this->generateGroupSources($tournament);

        $position = 1;

        /*
        |--------------------------------------------------------------------------
        | 1. Erste KO-Runde (mit Gruppenquellen)
        |--------------------------------------------------------------------------
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

                'is_third_place' => false,
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | 2. Weitere KO-Runden (Winner-Bracket)
        |--------------------------------------------------------------------------
        */

        for ($round = 2; $round <= $rounds; $round++) {

            $gamesInRound = $size / pow(2, $round);

            for ($position = 1; $position <= $gamesInRound; $position++) {

                Game::create([
                    'tournament_id' => $tournament->id,
                    'group_id' => null,

                    'player1_id' => null,
                    'player2_id' => null,

                    // Gewinner der vorherigen Spiele
                    'player1_source' => 'W' . ($position * 2 - 1),
                    'player2_source' => 'W' . ($position * 2),

                    'round' => $round,
                    'position' => $position,

                    'best_of' => $tournament->ko_best_of ?? 3,

                    'is_third_place' => false,
                ]);
            }
        }


        /*
        |--------------------------------------------------------------------------
        | 3. Spiel um Platz 3 (optional)
        |--------------------------------------------------------------------------
        */

        if ($tournament->has_third_place) {

            Game::create([
                'tournament_id' => $tournament->id,
                'group_id' => null,

                'player1_id' => null,
                'player2_id' => null,

                // Verlierer der Halbfinals
                'player1_source' => 'L1',
                'player2_source' => 'L2',

                'round' => $rounds,
                'position' => 99, // bewusst außerhalb normaler Struktur

                'best_of' => $tournament->ko_best_of ?? 3,

                'is_third_place' => true,
            ]);
        }
    }


    /**
     * ============================================================
     * SPIELER IN RUNDE 1 EINSETZEN
     * ============================================================
     *
     * OPTIONAL:
     *
     * Wird verwendet für:
     * - direktes KO-Turnier (ohne Gruppen)
     *
     * Bei group_ko:
     * → NICHT nötig (→ startKo nutzt Sources!)
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
     * ============================================================
     * GRUPPEN-SEEDS GENERIEREN
     * ============================================================
     *
     * Beispiel:
     *
     * Gruppen: A, B, C, D
     * Advance: 2
     *
     * Ergebnis:
     *
     * A1, B1, C1, D1, A2, B2, C2, D2
     *
     * Danach:
     * → wird durch Seeding-Logik verteilt
     */
    private function generateGroupSources(Tournament $tournament): array
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
     * ============================================================
     * SEEDING AUF BRACKET ANWENDEN
     * ============================================================
     *
     * Ziel:
     *
     * Klassisches Turnier-Seeding:
     *
     * 1 vs 8
     * 4 vs 5
     * 2 vs 7
     * 3 vs 6
     */
    private function buildBracketOrder(array $seeds): array
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
     * ============================================================
     * SEED-POSITIONEN BERECHNEN
     * ============================================================
     *
     * Beispiel für 8:
     *
     * 1, 8, 4, 5, 2, 7, 3, 6
     */
    private function generateSeedPositions(int $size): array
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
