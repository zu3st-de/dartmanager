<?php

namespace Tests\Feature;

use App\Http\Controllers\TournamentController;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

class LuckyLoserBracketTest extends TestCase
{
    use RefreshDatabase;

    public function test_lucky_loser_tournament_is_created_in_draft_without_bracket(): void
    {
        $user = User::factory()->create();

        $tournament = Tournament::create([
            'user_id' => $user->id,
            'name' => 'Hauptturnier',
            'mode' => 'group_ko',
            'status' => 'group_running',
            'group_count' => 2,
            'group_advance_count' => 1,
            'has_lucky_loser' => true,
        ]);

        $players = collect();

        foreach (range(1, 22) as $index) {
            $players->push($tournament->players()->create([
                'name' => 'Spieler '.$index,
                'seed' => $index,
            ]));
        }

        $tables = [
            'A' => [
                ['player' => ['id' => $players[0]->id]],
            ],
            'B' => [
                ['player' => ['id' => $players[1]->id]],
            ],
        ];

        $controller = app(TournamentController::class);
        $method = (new ReflectionClass($controller))->getMethod('createLuckyLoserTournament');
        $method->setAccessible(true);
        $method->invoke($controller, $tournament, $tables);

        $lucky = Tournament::where('parent_id', $tournament->id)
            ->where('type', 'lucky_loser')
            ->firstOrFail();

        $this->assertSame('draft', $lucky->status);
        $this->assertTrue((bool) $lucky->has_third_place);
        $this->assertSame(20, $lucky->players()->count());
        $this->assertSame(0, $lucky->games()->count());
    }
}
