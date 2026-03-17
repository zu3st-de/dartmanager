<?php

namespace App\Services;

use App\Models\Game;
use App\Models\Tournament;

/**
 * ================================================================
 * TournamentEngine
 * ================================================================
 *
 * Diese Engine steuert den Ablauf der KO-Phase.
 *
 * Verantwortlichkeiten:
 *
 * - Gewinner eines Spiels verarbeiten
 * - Gewinner in nächste Runde weiterleiten
 * - Verlierer ins Spiel um Platz 3 setzen (optional)
 * - Turnierende erkennen
 *
 * WICHTIG:
 *
 * - Gruppenspiele werden vollständig ignoriert
 * - KO-Logik greift ausschließlich bei Spielen ohne group_id
 *
 * ================================================================
 */
class TournamentEngine
{
    /**
     * ============================================================
     * HANDLE WIN
     * ============================================================
     *
     * Wird aufgerufen, sobald ein Spiel entschieden wurde.
     *
     * Ablauf:
     *
     * 1. Gruppenspiel?
     *    → Nur Ergebnis speichern, KEINE KO-Logik
     *
     * 2. KO-Spiel:
     *    → Gewinner speichern
     *    → ggf. weiterleiten
     *    → ggf. Platz-3-Spiel befüllen
     *    → ggf. Turnier beenden
     */
    public function handleWin(Game $game, int $winnerId): void
    {
        /*
        |--------------------------------------------------------------------------
        | 1. Gruppenspiele strikt isolieren
        |--------------------------------------------------------------------------
        |
        | EXTREM WICHTIG:
        | Gruppenspiele dürfen NIEMALS KO-Logik triggern.
        |
        */

        if ($this->isGroupMatch($game)) {

            $game->update([
                'winner_id' => $winnerId
            ]);

            return;
        }


        /*
        |--------------------------------------------------------------------------
        | 2. Gewinner im KO-Spiel speichern
        |--------------------------------------------------------------------------
        */

        $game->update([
            'winner_id' => $winnerId
        ]);

        $tournament = $game->tournament;
        $totalRounds = $this->getTotalRounds($tournament);


        /*
        |--------------------------------------------------------------------------
        | 3. Platz-3-Spiel selbst
        |--------------------------------------------------------------------------
        */

        if ($game->is_third_place) {

            $this->tryFinishTournament($tournament);
            return;
        }


        /*
        |--------------------------------------------------------------------------
        | 4. Finale
        |--------------------------------------------------------------------------
        */

        if ($game->round === $totalRounds && !$game->is_third_place) {

            /*
            |----------------------------------------------------------
            | Kein Platz 3 → direkt fertig
            |----------------------------------------------------------
            */

            if (!$tournament->has_third_place) {

                $tournament->update([
                    'status' => 'finished',
                    'winner_id' => $winnerId
                ]);

                return;
            }

            /*
            |----------------------------------------------------------
            | Platz 3 vorhanden → warten bis beide fertig
            |----------------------------------------------------------
            */

            $this->tryFinishTournament($tournament);
            return;
        }


        /*
        |--------------------------------------------------------------------------
        | 5. Normales KO-Spiel
        |--------------------------------------------------------------------------
        */

        $this->advanceWinner($game, $winnerId);


        /*
        |--------------------------------------------------------------------------
        | 6. Platz-3-Spiel befüllen (nur Halbfinale)
        |--------------------------------------------------------------------------
        */

        if ($tournament->has_third_place) {

            // Halbfinale = vorletzte Runde
            if ($game->round === $totalRounds - 1) {

                $loserId = $game->player1_id == $winnerId
                    ? $game->player2_id
                    : $game->player1_id;

                $thirdPlaceGame = Game::where('tournament_id', $tournament->id)
                    ->where('round', $totalRounds)
                    ->where('is_third_place', true)
                    ->first();

                if ($thirdPlaceGame) {

                    if (!$thirdPlaceGame->player1_id) {
                        $thirdPlaceGame->player1_id = $loserId;
                    } else {
                        $thirdPlaceGame->player2_id = $loserId;
                    }

                    $thirdPlaceGame->save();
                }
            }
        }
    }


    /**
     * ============================================================
     * TURNIER ABSCHLIESSEN
     * ============================================================
     *
     * Prüft ob:
     *
     * - Finale beendet ist
     * - (optional) Spiel um Platz 3 beendet ist
     *
     * → setzt Status auf "finished"
     */
    private function tryFinishTournament(Tournament $tournament): void
    {
        $totalRounds = $this->getTotalRounds($tournament);

        $finalFinished = Game::where('tournament_id', $tournament->id)
            ->where('round', $totalRounds)
            ->where('is_third_place', false)
            ->whereNotNull('winner_id')
            ->exists();

        if (!$finalFinished) {
            return;
        }

        if ($tournament->has_third_place) {

            $thirdFinished = Game::where('tournament_id', $tournament->id)
                ->where('is_third_place', true)
                ->whereNotNull('winner_id')
                ->exists();

            if (!$thirdFinished) {
                return;
            }
        }

        $tournament->update([
            'status' => 'finished'
        ]);
    }


    /**
     * ============================================================
     * GEWINNER WEITERLEITEN
     * ============================================================
     *
     * Setzt den Gewinner in das nächste KO-Spiel.
     *
     * Beispiel:
     *
     * Achtelfinale → Viertelfinale
     */
    private function advanceWinner(Game $game, int $winnerId): void
    {
        $tournament = $game->tournament;
        $totalRounds = $this->getTotalRounds($tournament);

        // Finale erreicht → nichts mehr zu tun
        if ($game->round >= $totalRounds) {
            return;
        }

        $nextRound = $game->round + 1;
        $nextPosition = (int) ceil($game->position / 2);

        $nextGame = Game::where('tournament_id', $game->tournament_id)
            ->where('round', $nextRound)
            ->where('position', $nextPosition)
            ->whereNull('group_id')          // nur KO
            ->where('is_third_place', false)
            ->first();

        if (!$nextGame) {
            return;
        }

        // Position bestimmt Slot (links/rechts im Baum)
        if ($game->position % 2 === 1) {
            $nextGame->player1_id = $winnerId;
        } else {
            $nextGame->player2_id = $winnerId;
        }

        $nextGame->save();
    }


    /**
     * ============================================================
     * GRUPPENSPIEL ERKENNEN
     * ============================================================
     *
     * WICHTIG:
     *
     * NICHT über round prüfen!
     *
     * → einzig sichere Methode ist group_id
     */
    private function isGroupMatch(Game $game): bool
    {
        return $game->group_id !== null;
    }


    /**
     * ============================================================
     * ANZAHL KO-RUNDEN
     * ============================================================
     *
     * Beispiel:
     *
     * 8 Spieler → 3 Runden
     * 16 Spieler → 4 Runden
     */
    private function getTotalRounds(Tournament $tournament): int
    {
        return (int) Game::where('tournament_id', $tournament->id)
            ->whereNull('group_id') // nur KO
            ->max('round');
    }
}
