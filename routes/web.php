<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TournamentController;
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
| 🌍 Öffentliche Seiten (kein Login nötig)
|--------------------------------------------------------------------------
*/
Route::get('/follow/{tournament}', [PublicController::class, 'follow'])
    ->name('tournament.follow');

Route::get('/follow/{tournament}/data', [PublicController::class, 'followData']);

Route::get('/tv/{tournament}', [TvController::class, 'show'])
    ->name('tv.tournament');

Route::get('/tv/{tournament}/data', [TvController::class, 'data'])
    ->name('tv.tournament.data');

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
| 🔒 Auth-Bereich (nur eingeloggte User)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | 👤 Profil
    |--------------------------------------------------------------------------
    */
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');


    /*
    |--------------------------------------------------------------------------
    | 📦 Archiv (WICHTIG: vor show!)
    |--------------------------------------------------------------------------
    */
    Route::get('/tournaments/archive', [TournamentController::class, 'archiveList'])
        ->name('tournaments.archive');

    Route::post('/tournaments/{tournament}/archive', [TournamentController::class, 'archive'])
        ->name('tournaments.archive.store');

    Route::post('/tournaments/{tournament}/restore', [TournamentController::class, 'restore'])
        ->name('tournaments.restore');


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

    // ❗ MUSS NACH archive stehen!
    Route::get('/tournaments/{tournament}', [TournamentController::class, 'show'])
        ->name('tournaments.show');


    /*
    |--------------------------------------------------------------------------
    | 👥 Spieler im Turnier
    |--------------------------------------------------------------------------
    */
    Route::post('/tournaments/{tournament}/players', [TournamentController::class, 'addPlayer'])
        ->name('tournaments.players.store');

    Route::post('/tournaments/{tournament}/bulk-players', [TournamentController::class, 'bulkPlayers'])
        ->name('tournaments.bulkPlayers');


    /*
    |--------------------------------------------------------------------------
    | ▶️ Turnier starten / steuern
    |--------------------------------------------------------------------------
    */
    Route::post('/tournaments/{tournament}/start', [TournamentController::class, 'start'])
        ->name('tournaments.start');

    Route::post('/tournaments/{tournament}/draw', [TournamentController::class, 'draw'])
        ->name('tournaments.draw');

    Route::post('/tournaments/{tournament}/start-ko', [TournamentController::class, 'startKo'])
        ->name('tournaments.startKo');

    Route::post('/tournaments/{tournament}/finish-groups', [TournamentController::class, 'finishGroups'])
        ->name('tournaments.finishGroups');


    /*
    |--------------------------------------------------------------------------
    | ⚙️ Einstellungen (Best-of etc.)
    |--------------------------------------------------------------------------
    */
    Route::post('/tournaments/{tournament}/group-best-of', [TournamentController::class, 'updateGroupBestOf'])
        ->name('tournaments.updateGroupBestOf');

    Route::post('/tournaments/{tournament}/round/{round}/bestof', [TournamentController::class, 'updateRoundBestOf'])
        ->name('round.updateBestOf');

    Route::patch('/tournaments/{tournament}/round/{round}/best-of', [TournamentController::class, 'updateRoundBestOf'])
        ->name('tournaments.updateRoundBestOf');


    /*
    |--------------------------------------------------------------------------
    | 🔄 Reset / Tools
    |--------------------------------------------------------------------------
    */
    Route::post('/tournaments/{tournament}/reset', [TournamentController::class, 'reset'])
        ->name('tournaments.reset');

    Route::post('/tournaments/{tournament}/reset-ko', [TournamentController::class, 'resetKo'])
        ->name('tournaments.resetKo');

    Route::post('/tournaments/{tournament}/reopen', [TournamentController::class, 'reopen'])
        ->name('tournaments.reopen');


    /*
    |--------------------------------------------------------------------------
    | 🎯 Spiele (Score / Reset)
    |--------------------------------------------------------------------------
    */
    Route::post('/games/{game}/score', [TournamentController::class, 'updateScore'])
        ->name('games.updateScore');

    Route::post('/games/{game}/reset', [TournamentController::class, 'resetGame'])
        ->name('games.reset');


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
    Route::get('/admin/tv', [TvController::class, 'manage']);
    Route::post('/admin/tv', [TvController::class, 'save']);


    /*
    |--------------------------------------------------------------------------
    | 🔥 KO GAME HTML (Live Update Partial)
    |--------------------------------------------------------------------------
    */
    Route::get('/games/{game}/html', function (App\Models\Game $game) {

        return view('tournaments.partials._ko_game_inner', [
            'game' => $game->fresh(['player1', 'player2']),
            'tournament' => $game->tournament,
            'maxRound' => $game->tournament->games()->max('round'),
        ]);
    });
});


/*
|--------------------------------------------------------------------------
| 🔄 Nächstes Spiel (KO)
|--------------------------------------------------------------------------
*/
Route::get('/games/{game}/next', function (App\Models\Game $game) {

    if ($game->group_id !== null || $game->round === null) {
        return response()->json(['next_game_id' => null]);
    }

    $nextGame = App\Models\Game::where('tournament_id', $game->tournament_id)
        ->where('round', $game->round + 1)
        ->where('position', (int) ceil($game->position / 2))
        ->first();

    return response()->json([
        'next_game_id' => $nextGame?->id
    ]);
});


/*
|--------------------------------------------------------------------------
| 🧩 Gruppen (AJAX Partial Views)
|--------------------------------------------------------------------------
*/
Route::get('/groups/{group}/table', function (\App\Models\Group $group) {

    return view('tournaments.partials._group_table', [
        'group' => $group->load('players', 'games.player1', 'games.player2'),
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
| 🔗 Auth Routes (Laravel Default)
|--------------------------------------------------------------------------
*/
require __DIR__ . '/auth.php';
