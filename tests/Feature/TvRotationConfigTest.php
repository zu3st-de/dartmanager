<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\TvTournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TvRotationConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_tv_rotation_config_returns_live_pages_and_rotation_time(): void
    {
        $user = User::factory()->create();

        $main = Tournament::create([
            'user_id' => $user->id,
            'name' => 'Hauptturnier',
            'mode' => 'ko',
            'status' => 'ko_running',
        ]);

        $lucky = Tournament::create([
            'user_id' => $user->id,
            'name' => 'Lucky Loser',
            'mode' => 'ko',
            'status' => 'ko_running',
            'parent_id' => $main->id,
            'type' => 'lucky_loser',
        ]);

        TvTournament::create([
            'user_id' => $user->id,
            'tournament_id' => $main->id,
            'position' => 1,
            'rotation_time' => 9,
        ]);

        TvTournament::create([
            'user_id' => $user->id,
            'tournament_id' => $lucky->id,
            'position' => 2,
            'rotation_time' => 9,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson(route('tv.rotation-config'));

        $response
            ->assertOk()
            ->assertHeader('Pragma', 'no-cache')
            ->assertHeader('Expires', '0')
            ->assertJsonPath('rotation_time', 9)
            ->assertJsonCount(3, 'pages')
            ->assertJsonPath('pages.0.type', 'overview')
            ->assertJsonPath('pages.1.public_id', $main->public_id)
            ->assertJsonPath('pages.2.public_id', $lucky->public_id);

        $cacheControl = (string) $response->headers->get('Cache-Control');

        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);

        $this->assertNotEmpty($response->json('overview_html'));
        $this->assertNotEmpty($response->json('signature'));
    }
}
