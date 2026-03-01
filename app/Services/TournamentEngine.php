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

        if ($game->round === $totalRounds) {

            // Kein Platz 3 aktiviert → direkt beenden
            if (!$tournament->has_third_place) {
                $tournament->update(['status' => 'finished']);
                return;
            }

            // Platz 3 existiert → prüfen ob auch fertig
            $this->tryFinishTournament($tournament);
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Normales KO-Spiel
        |--------------------------------------------------------------------------
        */

        $this->advanceWinner($game, $winnerId);

        if ($this->shouldCreateThirdPlace($game)) {
            $this->createThirdPlaceGame($tournament);
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
        $nextRound = $game->round + 1;
        $nextPosition = ceil($game->position / 2);

        $nextGame = Game::firstOrCreate(
            [
                'tournament_id' => $game->tournament_id,
                'round' => $nextRound,
                'position' => $nextPosition,
                'is_third_place' => false
            ]
        );

        if ($game->position % 2 === 1) {
            $nextGame->update(['player1_id' => $winnerId]);
        } else {
            $nextGame->update(['player2_id' => $winnerId]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Platz-3 Spiel erzeugen (nur nach beiden Halbfinals)
    |--------------------------------------------------------------------------
    */

    private function shouldCreateThirdPlace(Game $game): bool
    {
        $tournament = $game->tournament;

        if (!$tournament->has_third_place) {
            return false;
        }

        $totalRounds = $this->getTotalRounds($tournament);

        // Nur nach Halbfinale
        return $game->round === $totalRounds - 1;
    }

    private function createThirdPlaceGame(Tournament $tournament): void
    {
        $totalRounds = $this->getTotalRounds($tournament);

        // Halbfinalspiele
        $semiFinals = Game::where('tournament_id', $tournament->id)
            ->where('round', $totalRounds - 1)
            ->where('is_third_place', false)
            ->whereNotNull('winner_id')
            ->get();

        if ($semiFinals->count() !== 2) {
            return;
        }

        $losers = $semiFinals->map(function ($g) {
            return $g->winner_id === $g->player1_id
                ? $g->player2_id
                : $g->player1_id;
        });

        Game::firstOrCreate(
            [
                'tournament_id' => $tournament->id,
                'round' => $totalRounds,
                'position' => 99,
                'is_third_place' => true
            ],
            [
                'player1_id' => $losers->values()[0],
                'player2_id' => $losers->values()[1],
            ]
        );
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
        $firstRoundGames = Game::where('tournament_id', $tournament->id)
            ->where('round', 1)
            ->where('is_third_place', false)
            ->get();

        $playerCount = $firstRoundGames
            ->flatMap(fn($g) => [$g->player1_id, $g->player2_id])
            ->unique()
            ->count();

        return $playerCount > 0
            ? (int) log($playerCount, 2)
            : 0;
    }
}
