<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TournamentController;
use App\Http\Controllers\TournamentPlayerController;
use App\Http\Controllers\TournamentGameController;
use App\Http\Controllers\TournamentAdminController;

use App\Http\Controllers\PlayerController;
use App\Http\Controllers\TvController;
use App\Http\Controllers\PublicController;


/*
|--------------------------------------------------------------------------
| 🏠 Startseite
|--------------------------------------------------------------------------
*/

Route::get('/', fn() => view('welcome'));


/*
|--------------------------------------------------------------------------
| 🌍 Öffentliche Seiten
|--------------------------------------------------------------------------
*/

Route::get('/follow/{tournament:public_id}', [PublicController::class, 'follow'])
    ->name('tournament.follow');

Route::get('/follow/{tournament:public_id}/data', [PublicController::class, 'followData']);

Route::get('/tv/{tournament:public_id}', [TvController::class, 'show'])
    ->name('tv.tournament');

Route::get('/tv', [TvController::class, 'rotation']);


/*
|--------------------------------------------------------------------------
| 🔐 Dashboard
|--------------------------------------------------------------------------
*/

Route::get('/dashboard', fn() => view('dashboard'))
    ->middleware(['auth', 'verified'])
    ->name('dashboard');


/*
|--------------------------------------------------------------------------
| 🔒 Auth Bereich
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | 👤 Profil
    |--------------------------------------------------------------------------
    */

    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy');


    /*
    |--------------------------------------------------------------------------
    | 🏆 Turniere
    |--------------------------------------------------------------------------
    */

    Route::get('/tournaments', [TournamentController::class, 'index'])
        ->name('tournaments.index');

    Route::get('/tournaments/create', [TournamentController::class, 'create'])
        ->name('tournaments.create');

    Route::post('/tournaments', [TournamentController::class, 'store'])
        ->name('tournaments.store');

    Route::get('/tournaments/{tournament}', [TournamentController::class, 'show'])
        ->name('tournaments.show');


    /*
    |--------------------------------------------------------------------------
    | ▶️ Turniersteuerung
    |--------------------------------------------------------------------------
    */

    Route::post('/tournaments/{tournament}/start', [TournamentController::class, 'start'])
        ->name('tournaments.start');

    Route::post('/tournaments/{tournament}/start-ko', [TournamentController::class, 'startKo'])
        ->name('tournaments.startKo');

    Route::post('/tournaments/{tournament}/finish-groups', [TournamentController::class, 'finishGroups'])
        ->name('tournaments.finishGroups');

    Route::patch(
        '/tournaments/{tournament}/round-best-of',
        [TournamentController::class, 'updateRoundBestOf']
    )->name('tournaments.updateRoundBestOf');

    /*
    |--------------------------------------------------------------------------
    | 👥 Spieler
    |--------------------------------------------------------------------------
    */

    Route::post('/tournaments/{tournament}/players', [TournamentPlayerController::class, 'addPlayer'])
        ->name('tournaments.players.store');

    Route::post('/tournaments/{tournament}/bulk-players', [TournamentPlayerController::class, 'bulkPlayers'])
        ->name('tournaments.players.bulk');

    Route::post('/tournaments/{tournament}/draw', [TournamentPlayerController::class, 'draw'])
        ->name('tournaments.draw');


    /*
    |--------------------------------------------------------------------------
    | 🎯 Spiele
    |--------------------------------------------------------------------------
    */

    Route::post('/games/{game}/score', [TournamentGameController::class, 'updateScore'])
        ->name('games.score');

    Route::post('/games/{game}/reset', [TournamentGameController::class, 'resetGame'])
        ->name('games.reset');

    Route::get('/games/{game}/reload', [TournamentGameController::class, 'reloadGame'])
        ->name('games.reload');
    Route::get('/games/{game}/next', [TournamentGameController::class, 'nextGame']);


    /*
    |--------------------------------------------------------------------------
    | ⚙️ Admin
    |--------------------------------------------------------------------------
    */

    Route::post('/tournaments/{tournament}/reset', [TournamentAdminController::class, 'reset'])
        ->name('tournaments.reset');

    // ✅ FIX: resetKo gehört zum TournamentController
    Route::post('/tournaments/{tournament}/reset-ko', [TournamentController::class, 'resetKo'])
        ->name('tournaments.resetKo');

    Route::post('/tournaments/{tournament}/reopen', [TournamentAdminController::class, 'reopen'])
        ->name('tournaments.reopen');


    /*
    |--------------------------------------------------------------------------
    | 📦 Archiv
    |--------------------------------------------------------------------------
    */

    Route::get('/tournaments/archive', [TournamentAdminController::class, 'archiveList'])
        ->name('tournaments.archive');

    Route::post('/tournaments/{tournament}/archive', [TournamentAdminController::class, 'archive'])
        ->name('tournaments.archive.store');

    Route::post('/tournaments/{tournament}/restore', [TournamentAdminController::class, 'restore'])
        ->name('tournaments.restore');


    /*
    |--------------------------------------------------------------------------
    | 👥 Spieler bearbeiten
    |--------------------------------------------------------------------------
    */

    Route::patch('/players/{player}', [PlayerController::class, 'update'])
        ->name('players.update');

    Route::delete('/players/{player}', [PlayerController::class, 'destroy'])
        ->name('players.destroy');


    /*
    |--------------------------------------------------------------------------
    | 📺 TV Admin
    |--------------------------------------------------------------------------
    */

    Route::get('/admin/tv', [TvController::class, 'manage'])
        ->name('tv.manage');

    Route::post('/admin/tv', [TvController::class, 'save'])
        ->name('tv.save');
});


/*
|--------------------------------------------------------------------------
| 🔄 Game Reload (AJAX)
|--------------------------------------------------------------------------
*/

Route::get('/games/{game}/html', function (\App\Models\Game $game) {

    $game->load('player1', 'player2');

    if ($game->group_id) {
        return view('tournaments.partials._group_game_single', [
            'game' => $game,
            'tournament' => $game->tournament
        ]);
    }

    return view('tournaments.partials._ko_game_inner', [
        'game' => $game,
        'tournament' => $game->tournament
    ]);
});


/*
|--------------------------------------------------------------------------
| 🧩 Gruppen AJAX
|--------------------------------------------------------------------------
*/

Route::get('/groups/{group}/table', function (\App\Models\Group $group) {

    return view('tournaments.partials._group_table', [
        'group' => $group->load(
            'players',
            'games.player1',
            'games.player2'
        ),
        'tournament' => $group->tournament
    ]);
});

Route::get('/groups/{group}/games', function (\App\Models\Group $group) {

    $group = \App\Models\Group::with([
        'games.player1',
        'games.player2'
    ])->findOrFail($group->id);

    return view('tournaments.partials._group_games', [
        'group' => $group,
        'tournament' => $group->tournament
    ]);
});


/*
|--------------------------------------------------------------------------
| 🧩 KO AJAX
|--------------------------------------------------------------------------
*/

Route::get('/tournaments/{tournament}/bracket', function (\App\Models\Tournament $tournament) {

    return view('tournaments.partials.knockout', [
        'tournament' => $tournament->load(
            'games.player1',
            'games.player2'
        )
    ]);
})->name('tournaments.bracket');


/*
|--------------------------------------------------------------------------
| 🔐 Auth
|--------------------------------------------------------------------------
*/

require __DIR__ . '/auth.php';
