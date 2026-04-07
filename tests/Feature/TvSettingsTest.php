<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\TvTournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
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

    public function test_tournament_can_be_toggled_for_tv_from_the_tournament_list(): void
    {
        $user = User::factory()->create();

        $tournament = Tournament::create([
            'user_id' => $user->id,
            'name' => 'Feierabend Cup',
            'mode' => 'ko',
            'status' => 'draft',
        ]);

        $this->actingAs($user)
            ->from('/tournaments')
            ->post("/tournaments/{$tournament->public_id}/tv-toggle")
            ->assertRedirect('/tournaments');

        $this->assertDatabaseHas('tv_tournaments', [
            'user_id' => $user->id,
            'tournament_id' => $tournament->id,
            'position' => 1,
            'rotation_time' => 20,
        ]);

        $this->actingAs($user)
            ->from('/tournaments')
            ->post("/tournaments/{$tournament->public_id}/tv-toggle")
            ->assertRedirect('/tournaments');

        $this->assertDatabaseMissing('tv_tournaments', [
            'user_id' => $user->id,
            'tournament_id' => $tournament->id,
        ]);
    }

    public function test_archived_tournament_cannot_be_toggled_for_tv(): void
    {
        $user = User::factory()->create();

        $tournament = Tournament::create([
            'user_id' => $user->id,
            'name' => 'Archiv Cup',
            'mode' => 'ko',
            'status' => 'archived',
        ]);

        $this->actingAs($user)
            ->from('/tournaments')
            ->post("/tournaments/{$tournament->public_id}/tv-toggle")
            ->assertRedirect('/tournaments')
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('tv_tournaments', [
            'tournament_id' => $tournament->id,
        ]);
    }

    public function test_archiving_a_tournament_removes_it_from_tv_rotation(): void
    {
        $user = User::factory()->create();

        $tournament = Tournament::create([
            'user_id' => $user->id,
            'name' => 'TV Archiv Test',
            'mode' => 'ko',
            'status' => 'draft',
        ]);

        TvTournament::create([
            'user_id' => $user->id,
            'tournament_id' => $tournament->id,
            'position' => 1,
            'rotation_time' => 20,
        ]);

        $this->actingAs($user)
            ->post("/tournaments/{$tournament->public_id}/archive")
            ->assertRedirect('/tournaments');

        $this->assertDatabaseHas('tournaments', [
            'id' => $tournament->id,
            'status' => 'archived',
        ]);

        $this->assertDatabaseMissing('tv_tournaments', [
            'tournament_id' => $tournament->id,
        ]);
    }

    public function test_tv_routes_require_authentication(): void
    {
        $tournament = Tournament::create([
            'user_id' => User::factory()->create()->id,
            'name' => 'TV Cup',
            'mode' => 'ko',
            'status' => 'draft',
        ]);

        $this->get('/tv')->assertRedirect('/login');
        $this->get("/tv/{$tournament->public_id}")->assertRedirect('/login');
    }

    public function test_lucky_loser_tv_entry_is_not_duplicated_when_regenerated(): void
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

        foreach (range(1, 6) as $index) {
            $players->push($tournament->players()->create([
                'name' => 'Spieler '.$index,
                'seed' => $index,
            ]));
        }

        $tables = [
            'A' => [['player' => ['id' => $players[0]->id]]],
            'B' => [['player' => ['id' => $players[1]->id]]],
        ];

        $controller = app(\App\Http\Controllers\TournamentController::class);
        $method = (new ReflectionClass($controller))->getMethod('createLuckyLoserTournament');
        $method->setAccessible(true);

        $method->invoke($controller, $tournament, $tables);
        $method->invoke($controller, $tournament, $tables);

        $lucky = Tournament::where('parent_id', $tournament->id)
            ->where('type', 'lucky_loser')
            ->firstOrFail();

        $this->assertSame(1, TvTournament::where('tournament_id', $lucky->id)->count());
    }
}
