<?php

namespace App\Services;

use App\Models\Game;
use App\Models\Tournament;

class TournamentEngine
{
    public function handleWin(Game $game, int $winnerId): void
    {
        // Gruppenspiele niemals weiterleiten
        if ($game->round == 0) {
            $game->update(['winner_id' => $winnerId]);
            return;
        }

        $tournament = $game->tournament;

        $koPlayerCount = Game::where('tournament_id', $tournament->id)
            ->where('round', 1)
            ->where('is_group_match', 0)   // 🔥 wichtig
            ->get()
            ->flatMap(fn($g) => [$g->player1_id, $g->player2_id])
            ->unique()
            ->count();

        $totalRounds = $koPlayerCount > 0 ? log($koPlayerCount, 2) : 0;

        // Gewinner setzen
        $game->update([
            'winner_id' => $winnerId
        ]);

        /*
    |--------------------------------------------------------------------------
    | 1. Finale → Turnier beenden
    |--------------------------------------------------------------------------
    */
        if ($game->round == $totalRounds) {
            $tournament->update([
                'status' => 'finished'
            ]);
            return;
        }

        /*
    |--------------------------------------------------------------------------
    | 2. Platz-3 Spiel → NICHT weiter vorrücken
    |--------------------------------------------------------------------------
    */
        if ($game->round > $totalRounds) {
            return;
        }

        /*
    |--------------------------------------------------------------------------
    | 3. Gewinner in nächste Runde schieben
    |--------------------------------------------------------------------------
    */
        $nextRound    = $game->round + 1;
        $nextPosition = ceil($game->position / 2);

        $nextGame = Game::firstOrCreate(
            [
                'tournament_id' => $tournament->id,
                'round'         => $nextRound,
                'position'      => $nextPosition,
            ],
            [
                'best_of' => $game->best_of
            ]
        );

        if ($game->position % 2 === 1) {
            $nextGame->update(['player1_id' => $winnerId]);
        } else {
            $nextGame->update(['player2_id' => $winnerId]);
        }

        /*
    |--------------------------------------------------------------------------
    | 4. Platz-3 erzeugen (nach beiden Halbfinals)
    |--------------------------------------------------------------------------
    */
        $isSemifinal = ($game->round == $totalRounds - 1);

        if ($tournament->has_third_place && $isSemifinal) {

            $finishedSemifinals = Game::where('tournament_id', $tournament->id)
                ->where('round', $game->round)
                ->whereNotNull('winner_id')
                ->get();

            if ($finishedSemifinals->count() === 2) {

                $losers = $finishedSemifinals->map(function ($g) {
                    return $g->winner_id == $g->player1_id
                        ? $g->player2_id
                        : $g->player1_id;
                });

                Game::firstOrCreate(
                    [
                        'tournament_id' => $tournament->id,
                        'round'         => $totalRounds + 1,
                        'position'      => 1,
                    ],
                    [
                        'player1_id' => $losers->values()[0],
                        'player2_id' => $losers->values()[1],
                        'best_of'    => $game->best_of,
                    ]
                );
            }
        }
    }

    private function advanceToNextRound(Game $game, int $winnerId, Tournament $tournament): void
    {
        $nextRound = $game->round + 1;
        $nextPosition = ceil($game->position / 2);

        $nextGame = Game::firstOrCreate([
            'tournament_id' => $tournament->id,
            'round' => $nextRound,
            'position' => $nextPosition,
        ]);

        if ($game->position % 2 === 1) {
            $nextGame->update(['player1_id' => $winnerId]);
        } else {
            $nextGame->update(['player2_id' => $winnerId]);
        }
    }

    private function handleThirdPlace(Game $game, int $winnerId, Tournament $tournament, int $totalRounds): void
    {
        if (!$tournament->has_third_place) {
            return;
        }

        $isSemifinal = ($game->round == $totalRounds - 1);

        if (!$isSemifinal) {
            return;
        }

        $semifinalLosers = Game::where('tournament_id', $tournament->id)
            ->where('round', $game->round)
            ->whereNotNull('winner_id')
            ->get()
            ->map(function ($g) {
                return $g->player1_id === $g->winner_id
                    ? $g->player2_id
                    : $g->player1_id;
            });

        if ($semifinalLosers->count() === 2) {
            Game::firstOrCreate([
                'tournament_id' => $tournament->id,
                'round' => $totalRounds + 1,
                'position' => 1,
            ], [
                'player1_id' => $semifinalLosers[0],
                'player2_id' => $semifinalLosers[1],
                'best_of' => $game->best_of,
            ]);
        }
    }
}
