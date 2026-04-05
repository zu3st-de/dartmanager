<?php

namespace App\Services\Tournament;

use App\Models\Tournament;
use Illuminate\Support\Facades\DB;

/**
 * ================================================================
 * TournamentResetService
 * ================================================================
 *
 * Verantwortlich für:
 *
 * - Komplettes Turnier resetten
 * - KO Phase resetten
 * - Turnier wieder öffnen
 *
 */

class TournamentResetService
{

    /*
    |--------------------------------------------------------------------------
    | Komplettes Turnier zurücksetzen
    |--------------------------------------------------------------------------
    */

    public function resetTournament(Tournament $tournament)
    {
        DB::transaction(function () use ($tournament) {

            // Spiele löschen
            $tournament->games()->delete();

            // Gruppen löschen
            $tournament->groups()->delete();

            // Status zurücksetzen
            $tournament->update([
                'status' => 'draft'
            ]);
        });
    }


    /*
    |--------------------------------------------------------------------------
    | KO Phase zurücksetzen
    |--------------------------------------------------------------------------
    */

    public function resetKo(Tournament $tournament)
    {
        DB::transaction(function () use ($tournament) {

            $games = $tournament->games()
                ->whereNull('group_id')
                ->get();

            foreach ($games as $game) {

                $game->update([
                    'player1_id' => null,
                    'player2_id' => null,
                    'player1_score' => null,
                    'player2_score' => null,
                    'winner_id' => null,
                ]);
            }

            $tournament->update([
                'status' => 'group_running'
            ]);
        });
    }


    /*
    |--------------------------------------------------------------------------
    | Turnier wieder öffnen
    |--------------------------------------------------------------------------
    */

    public function reopen(Tournament $tournament)
    {
        $status = $tournament->games()
            ->whereNull('group_id')
            ->exists()
            ? 'ko_running'
            : 'group_running';

        $tournament->update([
            'status' => $status,
            'winner_id' => null
        ]);
    }
}
