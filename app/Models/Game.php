<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
    ];

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
    public function games()
    {
        return $this->hasMany(Game::class);
    }
    public function group()
    {
        return $this->belongsTo(Group::class);
    }
}
