<?php

namespace App\Services\Tournament;

use App\Models\Tournament;
use App\Services\Group\GroupGenerator;
use App\Services\Knockout\KnockoutGenerator;
use Illuminate\Support\Facades\DB;

class TournamentStarter
{
    public function start(Tournament $tournament)
    {
        DB::transaction(function () use ($tournament) {

            if ($tournament->mode === 'group_ko') {

                app(GroupGenerator::class)
                    ->generate($tournament, $tournament->group_count);

                $size =
                    $tournament->group_count *
                    $tournament->group_advance_count;

                app(KnockoutGenerator::class)
                    ->generatePlaceholderBracket($tournament, $size);

                $tournament->update([
                    'status' => 'group_running'
                ]);
            } else {

                $players = $tournament->players()->get();

                $size = pow(2, ceil(log($players->count(), 2)));

                app(KnockoutGenerator::class)
                    ->generatePlaceholderBracket($tournament, $size);

                app(KnockoutGenerator::class)
                    ->fillBracketPlayers($tournament, $players);

                $tournament->update([
                    'status' => 'ko_running'
                ]);
            }
        });
    }
}
