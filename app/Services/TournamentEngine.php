<?php

namespace App\Services;

use App\Models\Game;
use App\Models\Tournament;

/**
 * ================================================================
 * KnockoutGenerator
 * ================================================================
 *
 * Verantwortlich für die Erstellung und Befüllung eines KO-Baums.
 *
 * Hauptaufgaben:
 * - Generieren eines vollständigen Brackets (2^n Größe)
 * - Platzieren von Spielern im Baum
 * - Umgang mit BYEs (Freilosen)
 *
 * WICHTIG:
 * - Diese Klasse erstellt NUR die Struktur
 * - Spielausgänge werden durch TournamentEngine verarbeitet
 *
 * Typischer Ablauf:
 * 1. generatePlaceholderBracket()
 * 2. fillBracketPlayers()
 * 3. BYEs automatisch weiterleiten
 *
 * Diese Klasse enthält KEINE Business-Logik zur Qualifikation!
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

        // 🔥 Finale laden
        $finalGame = Game::where('tournament_id', $tournament->id)
            ->where('is_third_place', false)
            ->whereNull('group_id')
            ->orderByDesc('round')
            ->first();

        // ❌ kein Finale oder nicht entschieden
        if (!$finalGame || !$finalGame->winner_id) {
            return;
        }

        // 🔥 Kein Platz 3 → sofort fertig
        if (!$tournament->has_third_place) {
            $tournament->update(['status' => 'finished']);
            return;
        }

        // 🔥 Platz 3 prüfen
        $thirdGame = Game::where('tournament_id', $tournament->id)
            ->where('is_third_place', true)
            ->first();

        if (!$thirdGame || !$thirdGame->winner_id) {
            return;
        }

        // 🔥 Beide fertig → Turnier fertig
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
