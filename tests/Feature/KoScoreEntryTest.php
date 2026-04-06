<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KoScoreEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_ko_score_cannot_be_saved_before_both_participants_are_known(): void
    {
        $user = User::factory()->create();

        $tournament = Tournament::create([
            'user_id' => $user->id,
            'name' => 'KO Eingabe Test',
            'mode' => 'ko',
            'status' => 'ko_running',
        ]);

        $player = $tournament->players()->create([
            'name' => 'Spieler 1',
        ]);

        $game = Game::create([
            'tournament_id' => $tournament->id,
            'group_id' => null,
            'player1_id' => $player->id,
            'player2_id' => null,
            'round' => 1,
            'position' => 1,
            'best_of' => 3,
            'is_third_place' => false,
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson(route('games.score', $game), [
                'player1_score' => 2,
                'player2_score' => 0,
            ]);

        $response
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => 'Ergebnisse können erst eingetragen werden, wenn beide Teilnehmer feststehen.',
            ]);

        $this->assertNull($game->fresh()->winner_id);
        $this->assertNull($game->fresh()->player1_score);
        $this->assertNull($game->fresh()->player2_score);
    }
}
