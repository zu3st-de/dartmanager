<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TvBracketVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_early_tv_rounds_are_hidden_but_later_rounds_remain_visible(): void
    {
        $user = User::factory()->create();

        $tournament = Tournament::create([
            'user_id' => $user->id,
            'name' => 'TV KO',
            'mode' => 'ko',
            'status' => 'ko_running',
        ]);

        $players = collect();

        foreach (range(1, 32) as $index) {
            $players->push($tournament->players()->create([
                'name' => 'Spieler ' . $index,
            ]));
        }

        foreach (range(1, 16) as $position) {
            Game::create([
                'tournament_id' => $tournament->id,
                'group_id' => null,
                'player1_id' => $players[($position - 1) * 2]->id,
                'player2_id' => $players[(($position - 1) * 2) + 1]->id,
                'winner_id' => $players[($position - 1) * 2]->id,
                'round' => 1,
                'position' => $position,
                'best_of' => 3,
                'is_third_place' => false,
            ]);
        }

        foreach (range(1, 8) as $position) {
            Game::create([
                'tournament_id' => $tournament->id,
                'group_id' => null,
                'player1_id' => $players[($position - 1) * 4]->id,
                'player2_id' => $players[(($position - 1) * 4) + 2]->id,
                'winner_id' => null,
                'round' => 2,
                'position' => $position,
                'best_of' => 3,
                'is_third_place' => false,
            ]);
        }

        foreach (range(1, 4) as $position) {
            Game::create([
                'tournament_id' => $tournament->id,
                'group_id' => null,
                'player1_id' => null,
                'player2_id' => null,
                'winner_id' => null,
                'round' => 3,
                'position' => $position,
                'best_of' => 3,
                'is_third_place' => false,
            ]);
        }

        foreach (range(1, 2) as $position) {
            Game::create([
                'tournament_id' => $tournament->id,
                'group_id' => null,
                'player1_id' => null,
                'player2_id' => null,
                'winner_id' => null,
                'round' => 4,
                'position' => $position,
                'best_of' => 3,
                'is_third_place' => false,
            ]);
        }

        Game::create([
            'tournament_id' => $tournament->id,
            'group_id' => null,
            'player1_id' => null,
            'player2_id' => null,
            'winner_id' => null,
            'round' => 5,
            'position' => 1,
            'best_of' => 3,
            'is_third_place' => false,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('tv.tournament', $tournament));

        $response->assertOk();

        $rounds = $response->viewData('rounds');

        $this->assertFalse($rounds->has(1));
        $this->assertTrue($rounds->has(2));
        $this->assertTrue($rounds->has(3));
        $this->assertTrue($rounds->has(4));
        $this->assertTrue($rounds->has(5));
    }
}
