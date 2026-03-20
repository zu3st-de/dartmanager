<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TvTournament extends Model
{
    protected $fillable = [
        'tournament_id',
        'position',
        'rotation_time'
    ];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }
}
