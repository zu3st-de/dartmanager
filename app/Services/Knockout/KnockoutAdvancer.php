<?php

namespace App\Services\Knockout;

use App\Models\Game;
use App\Models\Tournament;

/**
 * ================================================================
 * KnockoutAdvancer
 * ================================================================
 *
 * Verantwortlich für:
 *
 * - Gewinner speichern
 * - Gewinner weiterleiten
 * - Platz-3 Spiel befüllen
 * - Turnier abschließen
 * - Reload IDs zurückgeben
 *
 * Wird aufgerufen nach Ergebnis-Eingabe eines Spiels
 *
 */

class KnockoutAdvancer
{
    /**
     * ============================================================
     * HANDLE WIN
     * ============================================================
     *
     * Wird aufgerufen sobald ein Spiel entschieden wurde
     *
     * Rückgabe:
     * - reload → Games neu laden
     * - fullReload → kompletter Reload nötig
     *
     */
    public function handleWin(Game $game, int $winnerId): array
    {
        $reload = [];
        $finished = false;

        /*
        |--------------------------------------------------------------------------
        | 1. Gruppenspiele strikt isolieren
        |--------------------------------------------------------------------------
        */

        if ($this->isGroupMatch($game)) {

            $game->update([
                'winner_id' => $winnerId
            ]);

            return [
                'reload' => [],
                'fullReload' => false
            ];
        }


        /*
        |--------------------------------------------------------------------------
        | 2. Gewinner speichern
        |--------------------------------------------------------------------------
        */

        $game->update([
            'winner_id' => $winnerId
        ]);

        $tournament = $game->tournament;
        $totalRounds = $this->getTotalRounds($tournament);


        /*
        |--------------------------------------------------------------------------
        | 3. Spiel um Platz 3 selbst
        |--------------------------------------------------------------------------
        */

        if ($game->is_third_place) {

            $finished = $this->tryFinishTournament($tournament);

            return [
                'reload' => [],
                'fullReload' => $finished
            ];
        }


        /*
        |--------------------------------------------------------------------------
        | 4. Finale
        |--------------------------------------------------------------------------
        */

        if ($game->round === $totalRounds && !$game->is_third_place) {

            if (!$tournament->has_third_place) {

                $tournament->update([
                    'status' => 'finished',
                    'winner_id' => $winnerId
                ]);

                return [
                    'reload' => [],
                    'fullReload' => true
                ];
            }

            $finished = $this->tryFinishTournament($tournament);

            return [
                'reload' => [],
                'fullReload' => $finished
            ];
        }


        /*
        |--------------------------------------------------------------------------
        | 5. Gewinner weiterleiten
        |--------------------------------------------------------------------------
        */

        $nextGame = $this->advanceWinner($game, $winnerId);

        if ($nextGame) {
            $reload[] = $nextGame->id;
        }


        /*
        |--------------------------------------------------------------------------
        | 6. Platz-3 Spiel befüllen (Halbfinale)
        |--------------------------------------------------------------------------
        */

        if ($tournament->has_third_place) {

            // Halbfinale = vorletzte Runde
            if ($game->round === $totalRounds - 1) {

                $loserId = $game->player1_id == $winnerId
                    ? $game->player2_id
                    : $game->player1_id;

                $thirdPlaceGame = Game::where('tournament_id', $tournament->id)
                    ->where('is_third_place', true)
                    ->first();

                if ($thirdPlaceGame) {

                    // Position bestimmt Slot
                    if ($game->position % 2 === 1) {
                        $thirdPlaceGame->player1_id = $loserId;
                    } else {
                        $thirdPlaceGame->player2_id = $loserId;
                    }

                    $thirdPlaceGame->save();

                    $reload[] = $thirdPlaceGame->id;
                }
            }
        }

        return [
            'reload' => array_unique($reload),
            'fullReload' => $finished
        ];
    }


    /**
     * ============================================================
     * TURNIER ABSCHLIESSEN
     * ============================================================
     */

    private function tryFinishTournament(Tournament $tournament): bool
    {
        /*
        |--------------------------------------------------------------------------
        | Finale prüfen
        |--------------------------------------------------------------------------
        */

        $finalGame = Game::where('tournament_id', $tournament->id)
            ->where('is_third_place', false)
            ->whereNull('group_id')
            ->orderByDesc('round')
            ->first();

        if (!$finalGame || !$finalGame->winner_id) {
            return false;
        }


        /*
        |--------------------------------------------------------------------------
        | Kein Spiel um Platz 3
        |--------------------------------------------------------------------------
        */

        if (!$tournament->has_third_place) {

            $tournament->update([
                'status' => 'finished'
            ]);

            return true;
        }


        /*
        |--------------------------------------------------------------------------
        | Spiel um Platz 3 prüfen
        |--------------------------------------------------------------------------
        */

        $thirdGame = Game::where('tournament_id', $tournament->id)
            ->where('is_third_place', true)
            ->first();

        if (!$thirdGame || !$thirdGame->winner_id) {
            return false;
        }


        /*
        |--------------------------------------------------------------------------
        | Turnier beenden
        |--------------------------------------------------------------------------
        */

        $tournament->update([
            'status' => 'finished'
        ]);

        return true;
    }


    /**
     * ============================================================
     * GEWINNER WEITERLEITEN
     * ============================================================
     */

    private function advanceWinner(Game $game, int $winnerId): ?Game
    {
        $tournament = $game->tournament;
        $totalRounds = $this->getTotalRounds($tournament);

        if ($game->round >= $totalRounds) {
            return null;
        }

        $nextRound = $game->round + 1;
        $nextPosition = (int) ceil($game->position / 2);

        $winnerSource = 'W' . $game->position;

        $nextGame = Game::where('tournament_id', $game->tournament_id)
            ->where('round', $nextRound)
            ->whereNull('group_id')
            ->where('is_third_place', false)
            ->where(function ($query) use ($winnerSource) {
                $query->where('player1_source', $winnerSource)
                    ->orWhere('player2_source', $winnerSource);
            })
            ->first();

        if (!$nextGame) {
            $nextGame = Game::where('tournament_id', $game->tournament_id)
            ->where('round', $nextRound)
            ->where('position', $nextPosition)
            ->whereNull('group_id')
            ->where('is_third_place', false)
            ->first();
        }

        if (!$nextGame) {
            return null;
        }

        if ($nextGame->player1_source === $winnerSource) {
            $nextGame->player1_id = $winnerId;
        } elseif ($nextGame->player2_source === $winnerSource) {
            $nextGame->player2_id = $winnerId;
        } elseif ($game->position % 2 === 1) {
            $nextGame->player1_id = $winnerId;
        } else {
            $nextGame->player2_id = $winnerId;
        }

        $nextGame->save();

        return $nextGame;
    }


    /**
     * ============================================================
     * GRUPPENSPIEL ERKENNEN
     * ============================================================
     */

    private function isGroupMatch(Game $game): bool
    {
        return $game->group_id !== null;
    }


    /**
     * ============================================================
     * KO RUNDEN BERECHNEN
     * ============================================================
     */

    private function getTotalRounds(Tournament $tournament): int
    {
        return (int) Game::where('tournament_id', $tournament->id)
            ->whereNull('group_id')
            ->max('round');
    }
}
