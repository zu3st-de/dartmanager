<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Player extends Model
{
    protected $fillable = [
        'tournament_id',
        'group_id',
        'name',
        'seed',
    ];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }
}
