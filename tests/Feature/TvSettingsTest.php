<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\TvTournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TvSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_tv_settings_can_be_saved_without_selected_tournaments(): void
    {
        $user = User::factory()->create();

        Tournament::create([
            'user_id' => $user->id,
            'name' => 'Ligaabend',
            'mode' => 'ko',
        ]);

        $response = $this
            ->actingAs($user)
            ->from('/admin/tv')
            ->post('/admin/tv', [
                'rotation_time' => 5,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/admin/tv');

        $this->assertDatabaseCount('tv_tournaments', 0);
    }

    public function test_tv_settings_only_store_the_authenticated_users_tournaments(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $ownTournament = Tournament::create([
            'user_id' => $user->id,
            'name' => 'Vereinsturnier',
            'mode' => 'ko',
        ]);

        $foreignTournament = Tournament::create([
            'user_id' => $otherUser->id,
            'name' => 'Fremdes Turnier',
            'mode' => 'ko',
        ]);

        $response = $this
            ->actingAs($user)
            ->from('/admin/tv')
            ->post('/admin/tv', [
                'tournaments' => [$ownTournament->id, $foreignTournament->id],
                'rotation_time' => 15,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/admin/tv');

        $this->assertDatabaseCount('tv_tournaments', 1);
        $this->assertDatabaseHas('tv_tournaments', [
            'user_id' => $user->id,
            'tournament_id' => $ownTournament->id,
            'position' => 1,
            'rotation_time' => 15,
        ]);
        $this->assertDatabaseMissing('tv_tournaments', [
            'tournament_id' => $foreignTournament->id,
        ]);

        $this->assertSame(15, TvTournament::where('user_id', $user->id)->value('rotation_time'));
    }

    public function test_archived_tournaments_are_not_available_for_tv_settings(): void
    {
        $user = User::factory()->create();

        $activeTournament = Tournament::create([
            'user_id' => $user->id,
            'name' => 'Offenes Turnier',
            'mode' => 'ko',
            'status' => 'draft',
        ]);

        $archivedTournament = Tournament::create([
            'user_id' => $user->id,
            'name' => 'Archiviertes Turnier',
            'mode' => 'ko',
            'status' => 'archived',
        ]);

        $this->actingAs($user)
            ->get('/admin/tv')
            ->assertOk()
            ->assertSee('Offenes Turnier')
            ->assertDontSee('Archiviertes Turnier');

        $response = $this
            ->actingAs($user)
            ->from('/admin/tv')
            ->post('/admin/tv', [
                'tournaments' => [$activeTournament->id, $archivedTournament->id],
                'rotation_time' => 20,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/admin/tv');

        $this->assertDatabaseCount('tv_tournaments', 1);
        $this->assertDatabaseHas('tv_tournaments', [
            'user_id' => $user->id,
            'tournament_id' => $activeTournament->id,
        ]);
        $this->assertDatabaseMissing('tv_tournaments', [
            'tournament_id' => $archivedTournament->id,
        ]);
    }
}
