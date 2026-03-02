<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class Game extends Model
{
    protected $fillable = [
        'tournament_id',
        'group_id',
        'player1_id',
        'player2_id',
        'round',
        'position',
        'winner_id',
        'best_of',
        'player1_score',
        'player2_score',
        'winning_rest',
        'is_third_place',
    ];

    /*
    |--------------------------------------------------------------------------
    | Beziehungen
    |--------------------------------------------------------------------------
    */

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function player1()
    {
        return $this->belongsTo(Player::class, 'player1_id');
    }

    public function player2()
    {
        return $this->belongsTo(Player::class, 'player2_id');
    }

    public function winner()
    {
        return $this->belongsTo(Player::class, 'winner_id');
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Best Of Validierung
    |--------------------------------------------------------------------------
    */

    public function validateResult(
        int $player1Score,
        int $player2Score,
        ?int $winningRest = null
    ): void {

        $bestOf = $this->best_of;
        $needed = (int) ceil($bestOf / 2);

        // Keine negativen Werte
        if ($player1Score < 0 || $player2Score < 0) {
            throw ValidationException::withMessages([
                'score' => 'Negative Werte sind nicht erlaubt.'
            ]);
        }

        // =========================
        // BEST OF 1
        // =========================
        if ($bestOf === 1) {

            if ($player1Score + $player2Score !== 1) {
                throw ValidationException::withMessages([
                    'score' => 'Bei Best of 1 muss das Ergebnis 1:0 oder 0:1 sein.'
                ]);
            }

            // Nur Gruppenphase braucht Restpunkte
            if ($this->group_id !== null) {

                if ($winningRest === null) {
                    throw ValidationException::withMessages([
                        'winning_rest' => 'Restpunkte müssen bei Best of 1 angegeben werden.'
                    ]);
                }

                if ($winningRest < 0 || $winningRest > 501) {
                    throw ValidationException::withMessages([
                        'winning_rest' => 'Restpunkte müssen zwischen 0 und 501 liegen.'
                    ]);
                }
            }

            return;
        }

        // =========================
        // BEST OF > 1
        // =========================

        if ($player1Score > $needed || $player2Score > $needed) {
            throw ValidationException::withMessages([
                'score' => 'Zu viele Gewinnsätze für dieses Best Of.'
            ]);
        }

        if ($player1Score === $player2Score) {
            throw ValidationException::withMessages([
                'score' => 'Unentschieden ist nicht erlaubt.'
            ]);
        }

        if ($player1Score !== $needed && $player2Score !== $needed) {
            throw ValidationException::withMessages([
                'score' => 'Niemand hat die benötigten Gewinnsätze erreicht.'
            ]);
        }
    }
}
