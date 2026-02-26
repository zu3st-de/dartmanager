<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Player;
use App\Models\Game;


class Tournament extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'mode',
        'group_count',
        'group_advance_count',
        'has_lucky_loser',
        'has_third_place',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function players()
    {
        return $this->hasMany(Player::class);
    }

    public function games()
    {
        return $this->hasMany(Game::class);
    }

    public function groups()
    {
        return $this->hasMany(Group::class);
    }
}
