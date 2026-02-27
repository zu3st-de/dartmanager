<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TournamentController;

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
});


require __DIR__ . '/auth.php';
