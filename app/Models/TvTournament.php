<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TvTournament extends Model
{
    protected $fillable = [
        'user_id',
        'tournament_id',
        'position',
        'rotation_time',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }
}
