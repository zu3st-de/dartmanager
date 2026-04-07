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

    public function test_lucky_loser_bracket_creates_full_bracket_and_resolves_byes(): void
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

        $this->assertSame('ko_running', $lucky->status);
        $this->assertTrue((bool) $lucky->has_third_place);
        $this->assertSame(20, $lucky->players()->count());
        $this->assertSame(32, $lucky->games()->count());
        $this->assertSame(16, $lucky->games()->where('round', 1)->count());
        $this->assertSame(8, $lucky->games()->where('round', 2)->count());
        $this->assertSame(4, $lucky->games()->where('round', 3)->count());
        $this->assertSame(2, $lucky->games()->where('round', 4)->where('is_third_place', false)->count());
        $this->assertSame(2, $lucky->games()->where('round', 5)->count());
        $this->assertSame(1, $lucky->games()->where('round', 5)->where('is_third_place', true)->count());
        $this->assertSame(1, $lucky->games()->where('round', 5)->where('is_third_place', false)->count());
        $this->assertSame(12, $lucky->games()->where('round', 1)->whereNotNull('winner_id')->count());
    }
}
