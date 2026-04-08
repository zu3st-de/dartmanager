<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_tournament_reset_returns_tournament_to_draft(): void
    {
        $user = User::factory()->create();

        $tournament = Tournament::create([
            'user_id' => $user->id,
            'name' => 'Reset Test',
            'mode' => 'group_ko',
            'status' => 'group_running',
            'group_count' => 2,
            'group_advance_count' => 2,
        ]);

        $group = $tournament->groups()->create([
            'name' => 'A',
        ]);

        $tournament->games()->create([
            'group_id' => $group->id,
            'player1_id' => null,
            'player2_id' => null,
            'round' => 1,
            'position' => 1,
            'best_of' => 3,
            'is_third_place' => false,
        ]);

        $response = $this
            ->actingAs($user)
            ->from(route('tournaments.show', $tournament))
            ->post(route('tournaments.reset', $tournament), [
                'confirm_name' => $tournament->name,
            ]);

        $response->assertRedirect(route('tournaments.show', $tournament));

        $this->assertSame('draft', $tournament->fresh()->status);
        $this->assertSame(0, $tournament->games()->count());
        $this->assertSame(0, $tournament->groups()->count());
    }
}
