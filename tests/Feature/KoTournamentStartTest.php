<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KoTournamentStartTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_ko_start_creates_full_bracket_and_resolves_byes(): void
    {
        $user = User::factory()->create();

        $tournament = Tournament::create([
            'user_id' => $user->id,
            'name' => 'KO Test',
            'mode' => 'ko',
            'status' => 'draft',
        ]);

        foreach (range(1, 6) as $seed) {
            $tournament->players()->create([
                'name' => 'Spieler ' . $seed,
                'seed' => $seed,
            ]);
        }

        $response = $this
            ->actingAs($user)
            ->post(route('tournaments.start', $tournament));

        $response->assertRedirect(route('tournaments.show', $tournament));

        $this->assertSame('ko_running', $tournament->fresh()->status);
        $this->assertDatabaseCount('games', 7);
        $this->assertSame(4, $tournament->games()->where('round', 1)->count());
        $this->assertSame(2, $tournament->games()->where('round', 2)->count());
        $this->assertSame(1, $tournament->games()->where('round', 3)->count());
        $this->assertSame(2, $tournament->games()->where('round', 1)->whereNotNull('winner_id')->count());
    }
}
