<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TournamentController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\TvController;
use App\Http\Controllers\PublicController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');
Route::middleware('auth')->group(function () {
    Route::get(
        '/profile',
        [ProfileController::class, 'edit']
    )->name('profile.edit');
    Route::patch(
        '/profile',
        [ProfileController::class, 'update']
    )->name('profile.update');
    Route::delete(
        '/profile',
        [ProfileController::class, 'destroy']
    )->name('profile.destroy');
    Route::get(
        '/tournaments',
        [TournamentController::class, 'index']
    )->name('tournaments.index');
    Route::get(
        '/tournaments/create',
        [TournamentController::class, 'create']
    )->name('tournaments.create');
    Route::post(
        '/tournaments',
        [TournamentController::class, 'store']
    )->name('tournaments.store');
    Route::get(
        '/tournaments/{tournament}',
        [TournamentController::class, 'show']
    )->name('tournaments.show');
    Route::post(
        '/tournaments/{tournament}/players',
        [TournamentController::class, 'addPlayer']
    )->name('tournaments.players.store');
    Route::post(
        '/tournaments/{tournament}/start',
        [TournamentController::class, 'start']
    )
        ->name('tournaments.start');
    Route::post(
        '/tournaments/{tournament}/draw',
        [TournamentController::class, 'draw']
    )
        ->name('tournaments.draw');
    Route::post(
        '/games/{game}/score',
        [TournamentController::class, 'updateScore']
    )
        ->name('games.updateScore');
    Route::post(
        '/tournaments/{tournament}/round/{round}/bestof',
        [TournamentController::class, 'updateRoundBestOf']
    )
        ->name('round.updateBestOf');
    Route::post(
        '/tournaments/{tournament}/start-ko',
        [TournamentController::class, 'startKo']
    )
        ->name('tournaments.startKo');
    Route::post(
        '/tournaments/{tournament}/add-player',
        [TournamentController::class, 'addPlayer']
    )->name('tournaments.addPlayer');
    Route::post(
        '/tournaments/{tournament}/finish-groups',
        [TournamentController::class, 'finishGroups']
    )
        ->name('tournaments.finishGroups');
    Route::patch(
        '/tournaments/{tournament}/group-best-of',
        [TournamentController::class, 'updateGroupBestOf']
    )->name('tournaments.updateGroupBestOf');
    Route::patch(
        '/tournaments/{tournament}/round/{round}/best-of',
        [TournamentController::class, 'updateRoundBestOf']
    )->name('tournaments.updateRoundBestOf');
    Route::patch(
        '/players/{player}',
        [PlayerController::class, 'update']
    )
        ->name('players.update');
    Route::delete(
        '/players/{player}',
        [PlayerController::class, 'destroy']
    )
        ->name('players.destroy');
    Route::post(
        '/tournaments/{tournament}/reset',
        [TournamentController::class, 'reset']
    )
        ->name('tournaments.reset');
    Route::post(
        '/games/{game}/reset',
        [TournamentController::class, 'resetGame']
    )
        ->name('games.reset');
    Route::post(
        '/tournaments/{tournament}/reopen',
        [TournamentController::class, 'reopen']
    )
        ->name('tournaments.reopen');
    Route::post(
        '/tournaments/{tournament}/bulk-players',
        [TournamentController::class, 'bulkPlayers']
    )
        ->name('tournaments.bulkPlayers');
    Route::post(
        '/tournaments/{tournament}/reset-ko',
        [TournamentController::class, 'resetKo']
    )->name('tournaments.resetKo');
    Route::get(
        '/tv/{tournament}',
        [TvController::class, 'show']
    )->name('tv.tournament');
    Route::get(
        '/tv/{tournament}/data',
        [TvController::class, 'data']
    )->name('tv.tournament.data');
    Route::get(
        '/follow/{tournament}',
        [PublicController::class, 'follow']
    )
        ->name('tournament.follow');
    Route::get(
        '/follow/{tournament}/data',
        [PublicController::class, 'followData']
    );
    Route::get('/tv', [TvController::class, 'rotation']);
    Route::get('/tv', [TvController::class, 'rotation']);
    Route::get('/admin/tv', [TvController::class, 'manage']);
    Route::post('/admin/tv', [TvController::class, 'save']);
});


require __DIR__ . '/auth.php';
