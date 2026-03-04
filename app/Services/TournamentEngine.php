<?php

namespace App\Services;

use App\Models\Game;
use App\Models\Tournament;

class TournamentEngine
{
    public function handleWin(Game $game, int $winnerId): void
    {
        // Ergebnis speichern
        $game->update([
            'winner_id' => $winnerId
        ]);

        // Gruppenspiele ignorieren
        if ($this->isGroupMatch($game)) {
            return;
        }

        $tournament = $game->tournament;
        $totalRounds = $this->getTotalRounds($tournament);
        //dd($totalRounds);

        /*
        |--------------------------------------------------------------------------
        | Platz-3-Spiel
        |--------------------------------------------------------------------------
        */

        if ($game->is_third_place) {
            $this->tryFinishTournament($tournament);
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Finale
        |--------------------------------------------------------------------------
        */

        if ($game->round === $totalRounds && !$game->is_third_place) {

            // Kein Platz 3 aktiviert → direkt beenden
            if (!$tournament->has_third_place) {
                $tournament->update(['status' => 'finished']);
                return;
            }

            // Platz 3 existiert → prüfen ob auch fertig
            $this->tryFinishTournament($tournament);
            return;
            $tournament->update([
                'status' => 'finished',
                'winner_id' => $winnerId
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Normales KO-Spiel
        |--------------------------------------------------------------------------
        */

        $this->advanceWinner($game, $winnerId);
        if ($tournament->has_third_place) {

            $totalRounds = $this->getTotalRounds($tournament);

            // Wenn Halbfinale
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

    /*
    |--------------------------------------------------------------------------
    | Turnier beenden wenn alles fertig
    |--------------------------------------------------------------------------
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

        $tournament->update(['status' => 'finished']);
    }

    /*
    |--------------------------------------------------------------------------
    | Gewinner weiterleiten
    |--------------------------------------------------------------------------
    */

    private function advanceWinner(Game $game, int $winnerId): void
    {
        $tournament = $game->tournament;
        $totalRounds = $this->getTotalRounds($tournament);

        // Finale? → nichts weiterleiten
        if ($game->round >= $totalRounds) {
            return;
        }

        $nextRound = $game->round + 1;
        $nextPosition = (int) ceil($game->position / 2);

        // 🔥 Nur EXISTIERENDES Spiel holen
        $nextGame = Game::where('tournament_id', $game->tournament_id)
            ->where('round', $nextRound)
            ->where('position', $nextPosition)
            ->whereNull('group_id')          // nur KO
            ->where('is_third_place', false)
            ->first();

        if (!$nextGame) {
            return; // Sicherheitsabbruch
        }

        // Gewinner korrekt eintragen
        if ($game->position % 2 === 1) {
            $nextGame->player1_id = $winnerId;
        } else {
            $nextGame->player2_id = $winnerId;
        }

        $nextGame->save();
    }

    /*
    |--------------------------------------------------------------------------
    | Gruppenspiel?
    |--------------------------------------------------------------------------
    */

    private function isGroupMatch(Game $game): bool
    {
        return $game->round === 0;
    }

    /*
    |--------------------------------------------------------------------------
    | Anzahl KO-Runden
    |--------------------------------------------------------------------------
    */

    private function getTotalRounds(Tournament $tournament): int
    {
        return (int) Game::where('tournament_id', $tournament->id)
            ->whereNull('group_id') // nur KO
            ->max('round');
    }
}
