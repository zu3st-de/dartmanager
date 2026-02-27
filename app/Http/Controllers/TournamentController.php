<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\Game;
use Illuminate\Http\Request;
use App\Services\KnockoutGenerator;
use App\Services\TournamentEngine;
use App\Services\GroupGenerator;
use App\Services\GroupTableCalculator;

class TournamentController extends Controller
{
    public function index()
    {
        $tournaments = auth()->user()
            ->tournaments()
            ->latest()
            ->get();

        return view('tournaments.index', compact('tournaments'));
    }

    public function create()
    {
        return view('tournaments.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'mode' => 'required|in:ko,group_ko',
            'group_count' => 'nullable|integer|min:1',
            'group_advance_count' => 'nullable|integer|min:1',
        ]);

        // Pflichtfelder bei Gruppenphase
        if ($validated['mode'] === 'group_ko') {

            $request->validate([
                'group_count' => 'required|integer|min:1',
                'group_advance_count' => 'required|integer|min:1',
            ]);

            $qualifiedCount =
                $validated['group_count'] *
                $validated['group_advance_count'];

            $isPowerOfTwo =
                $qualifiedCount > 0 &&
                ($qualifiedCount & ($qualifiedCount - 1)) === 0;

            if (!$isPowerOfTwo) {
                return back()
                    ->withErrors([
                        'group_advance_count' =>
                        'Die Gesamtzahl der KO-Teilnehmer muss eine 2er-Potenz sein.'
                    ])
                    ->withInput();
            }
        }

        auth()->user()->tournaments()->create([
            'name' => $validated['name'],
            'mode' => $validated['mode'],
            'group_count' => $validated['group_count'] ?? null,
            'group_advance_count' => $validated['group_advance_count'] ?? null,
            'has_lucky_loser' => $request->has('has_lucky_loser'),
            'has_third_place' => $request->has('has_third_place'),
            'status' => 'draft',
        ]);

        return redirect()
            ->route('tournaments.index')
            ->with('success', 'Turnier erfolgreich erstellt.');
    }

    public function show(Tournament $tournament)
    {
        $this->authorizeTournament($tournament);

        $tournament->load([
            'players',
            'groups.players',
            'groups.games.player1',
            'groups.games.player2',
            'games.player1',
            'games.player2',
        ]);

        return view('tournaments.show', compact('tournament'));
    }

    public function addPlayer(Request $request, Tournament $tournament)
    {
        $this->authorizeTournament($tournament);

        if ($tournament->status !== 'draft') {
            abort(400);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $player = $tournament->players()->create([
            'name' => $validated['name'],
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'id' => $player->id,
                'name' => $player->name,
            ]);
        }

        return back();
    }

    public function draw(Tournament $tournament)
    {
        $this->authorizeTournament($tournament);

        if ($tournament->status !== 'draft') {
            abort(400);
        }

        $players = $tournament->players()
            ->inRandomOrder()
            ->get();

        foreach ($players as $index => $player) {
            $player->update([
                'seed' => $index + 1
            ]);
        }

        return back();
    }

    public function start(Tournament $tournament)
    {
        $this->authorizeTournament($tournament);

        if ($tournament->mode === 'group_ko') {

            if (
                !$tournament->group_count ||
                !$tournament->group_advance_count
            ) {
                abort(400);
            }

            app(GroupGenerator::class)
                ->generate($tournament, $tournament->group_count);

            $tournament->update([
                'status' => 'group_running'
            ]);

            return redirect()
                ->route('tournaments.show', $tournament);
        }

        if ($tournament->mode === 'ko') {

            app(KnockoutGenerator::class)
                ->generate($tournament);

            $tournament->update([
                'status' => 'ko_running'
            ]);
        }

        return redirect()
            ->route('tournaments.show', $tournament);
    }

    public function updateScore(Request $request, Game $game)
    {
        $tournament = $game->tournament;

        $this->authorizeTournament($tournament);

        // wenn Spiel schon entschieden → bei AJAX JSON zurückgeben
        if ($game->winner_id) {

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Spiel bereits entschieden'
                ]);
            }

            return back();
        }

        $validated = $request->validate([
            'player1_score' => 'required|integer|min:0',
            'player2_score' => 'required|integer|min:0',
        ]);

        $game->update([
            'player1_score' => $validated['player1_score'],
            'player2_score' => $validated['player2_score'],
        ]);

        $firstTo = ceil($game->best_of / 2);

        $winnerId = null;

        if ($game->player1_score >= $firstTo) {

            $winnerId = $game->player1_id;

            app(TournamentEngine::class)
                ->handleWin($game, $winnerId);
        }

        if ($game->player2_score >= $firstTo) {

            $winnerId = $game->player2_id;

            app(TournamentEngine::class)
                ->handleWin($game, $winnerId);
        }

        // Game neu laden (wichtig für AJAX)
        $game->refresh();

        // AJAX Response
        if ($request->expectsJson()) {

            return response()->json([
                'success' => true,
                'game_id' => $game->id,
                'winner_id' => $game->winner_id,
                'player1_score' => $game->player1_score,
                'player2_score' => $game->player2_score,
            ]);
        }

        return back();
    }

    public function updateRoundBestOf(
        Request $request,
        Tournament $tournament,
        $round
    ) {
        $this->authorizeTournament($tournament);

        $validated = $request->validate([
            'best_of' => [
                'required',
                'integer',
                'min:1',
                function ($attribute, $value, $fail) {
                    if ($value % 2 === 0) {
                        $fail('Best-of muss eine ungerade Zahl sein.');
                    }
                }
            ],
        ]);

        $hasFinishedGame = Game::where('tournament_id', $tournament->id)
            ->where('round', $round)
            ->whereNotNull('winner_id')
            ->exists();

        if ($hasFinishedGame) {
            return back()
                ->with('error', 'Best-of kann nicht mehr geändert werden.');
        }

        Game::where('tournament_id', $tournament->id)
            ->where('round', $round)
            ->update([
                'best_of' => $validated['best_of']
            ]);

        return back();
    }

    public function startKo(Tournament $tournament)
    {
        $this->authorizeTournament($tournament);

        if ($tournament->status !== 'group_running') {
            abort(400);
        }

        $qualified = collect();

        foreach ($tournament->groups as $group) {

            $table = app(GroupTableCalculator::class)
                ->calculate($group);

            $topPlayers = collect($table)
                ->take($tournament->group_advance_count)
                ->pluck('player');

            $qualified = $qualified->merge($topPlayers);
        }

        if ($qualified->count() < 2) {
            abort(400);
        }

        app(KnockoutGenerator::class)
            ->generateFromCollection($tournament, $qualified);

        $tournament->update([
            'status' => 'ko_running'
        ]);

        return redirect()
            ->route('tournaments.show', $tournament);
    }

    private function authorizeTournament(Tournament $tournament)
    {
        if ($tournament->user_id !== auth()->id()) {
            abort(403);
        }
    }
    public function finishGroups(Tournament $tournament)
    {
        $this->authorizeTournament($tournament);

        // prüfen ob alle Gruppenspiele fertig sind
        $unfinished = Game::where('tournament_id', $tournament->id)
            ->whereNotNull('group_id')
            ->whereNull('winner_id')
            ->exists();

        if ($unfinished) {
            return back()->with(
                'error',
                'Nicht alle Gruppenspiele sind abgeschlossen.'
            );
        }

        return $this->startKo($tournament);
    }
}
