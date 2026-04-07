<?php

namespace App\Services\Group;

use App\Models\Group;

/**
 * ================================================================
 * GroupTableCalculator
 * ================================================================
 *
 * Verantwortlich für die Berechnung der Gruppentabelle.
 *
 * Berechnet pro Spieler:
 *
 * - Spiele (played)
 * - Siege (wins)
 * - Niederlagen (losses)
 * - Punkte (points)
 * - Differenz (difference)
 *
 * Sortierung:
 *
 * 1. Punkte (desc)
 * 2. Differenz (desc)
 *
 * ================================================================
 */
class GroupTableCalculator
{
    /**
     * ============================================================
     * TABELLE BERECHNEN
     * ============================================================
     *
     * Gibt ein Array zurück:
     *
     * [
     *   [
     *     'player' => Player,
     *     'played' => int,
     *     'wins' => int,
     *     'losses' => int,
     *     'points' => int,
     *     'difference' => int,
     *   ],
     *   ...
     * ]
     */
    public function calculate(Group $group): array
    {
        /*
        |--------------------------------------------------------------------------
        | WICHTIG: Spiele einmal laden (Performance!)
        |--------------------------------------------------------------------------
        |
        | Verhindert N+1 Queries pro Spieler
        |
        */

        $games = $group->games()
            ->whereNotNull('winner_id')
            ->get();

        $table = [];

        foreach ($group->players as $player) {

            /*
            |--------------------------------------------------------------------------
            | Spiele des Spielers filtern
            |--------------------------------------------------------------------------
            */

            $playerGames = $games->filter(function ($game) use ($player) {
                return $game->player1_id === $player->id
                    || $game->player2_id === $player->id;
            });

            $wins = 0;
            $losses = 0;
            $points = 0;
            $difference = 0;

            foreach ($playerGames as $game) {

                $isWinner = $game->winner_id === $player->id;

                /*
                |--------------------------------------------------------------------------
                | Siege / Niederlagen / Punkte
                |--------------------------------------------------------------------------
                */

                if ($isWinner) {
                    $wins++;
                    $points++; // 1 Punkt pro Sieg
                } else {
                    $losses++;
                }

                /*
                |--------------------------------------------------------------------------
                | DIFFERENZBERECHNUNG
                |--------------------------------------------------------------------------
                |
                | Zwei Modi:
                |
                | 1. Best-of 1 → Restpunkte (Darts-typisch)
                | 2. Best-of >1 → Legs-Differenz
                |
                */

                if ($game->best_of == 1) {

                    $rest = $game->winning_rest ?? 0;

                    if ($isWinner) {
                        $difference += $rest;
                    } else {
                        $difference -= $rest;
                    }
                } else {

                    if ($player->id === $game->player1_id) {
                        $difference += $game->player1_score - $game->player2_score;
                    } else {
                        $difference += $game->player2_score - $game->player1_score;
                    }
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Tabellenzeile hinzufügen
            |--------------------------------------------------------------------------
            */

            $table[] = [
                'player' => $player,
                'played' => $wins + $losses,
                'wins' => $wins,
                'losses' => $losses,
                'points' => $points,
                'difference' => $difference,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | SORTIERUNG
        |--------------------------------------------------------------------------
        |
        | Reihenfolge:
        |
        | 1. Punkte
        | 2. Differenz
        |
        */

        usort($table, function ($a, $b) {

            // Punkte
            if ($a['points'] !== $b['points']) {
                return $b['points'] <=> $a['points'];
            }

            // Differenz
            if ($a['difference'] !== $b['difference']) {
                return $b['difference'] <=> $a['difference'];
            }

            /*
            |--------------------------------------------------------------------------
            | Optional: stabiler Fallback (z. B. Name)
            |--------------------------------------------------------------------------
            |
            | Verhindert zufällige Reihenfolge bei Gleichstand
            |
            */

            return strcmp($a['player']->name, $b['player']->name);
        });

        return $table;
    }
}
