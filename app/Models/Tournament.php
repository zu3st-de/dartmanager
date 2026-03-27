<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
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
        'group_best_of',
        'status',
        'parent_id',
        'type',
        'public_id',
    ];


    protected static function booted()
    {
        static::creating(function ($model) {

            do {
                $id = strtoupper(\Illuminate\Support\Str::random(6));
            } while (self::where('public_id', $id)->exists());

            $model->public_id = $id;
        });
    }
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
    /**
     * ================================================================
     * Child Turniere (z.B. Lucky Loser)
     * ================================================================
     */
    public function children()
    {
        return $this->hasMany(Tournament::class, 'parent_id');
    }
    /**
     * ================================================================
     * Parent Turnier
     * ================================================================
     */
    public function parent()
    {
        return $this->belongsTo(Tournament::class, 'parent_id');
    }
}
