<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\Game;
use App\Models\Player;
use Illuminate\Http\Request;
use App\Services\KnockoutGenerator;
use App\Services\TournamentEngine;

class TournamentController extends Controller
{
    public function index()
    {
        $tournaments = auth()->user()->tournaments()->latest()->get();

        return view('tournaments.index', compact('tournaments'));
    }

    public function create()
    {
        return view('tournaments.create');
    }

    public function store(Request $request)
    {
        // Grundvalidierung
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'mode' => 'required|in:ko,group_ko',
            'group_count' => 'nullable|integer|min:1',
            'group_advance_count' => 'nullable|integer|min:1',
        ]);

        // 🔥 Zusätzliche Pflichtprüfung für Gruppenphase
        if ($validated['mode'] === 'group_ko') {

            $request->validate([
                'group_count' => 'required|integer|min:1',
                'group_advance_count' => 'required|integer|min:1',
            ]);

            // Optional: direkt prüfen ob KO-Teilnehmer 2er-Potenz ergeben
            $qualifiedCount = $validated['group_count'] * $validated['group_advance_count'];

            $isPowerOfTwo = $qualifiedCount > 0 && ($qualifiedCount & ($qualifiedCount - 1)) === 0;

            if (!$isPowerOfTwo) {
                return back()
                    ->withErrors([
                        'group_advance_count' => 'Die Gesamtzahl der KO-Teilnehmer muss eine 2er-Potenz sein.'
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

        return redirect()->route('tournaments.index')
            ->with('success', 'Turnier erfolgreich erstellt.');
    }

    public function show(Tournament $tournament)
    {
        if ($tournament->user_id !== auth()->id()) {
            abort(403);
        }

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
        if ($tournament->user_id !== auth()->id()) {
            abort(403);
        }
        if ($tournament->user_id !== auth()->id()) {
            abort(403);
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


    public function start(Tournament $tournament)
    {
        if ($tournament->user_id !== auth()->id()) {
            abort(403);
        }

        if (
            $tournament->mode === 'group_ko' &&
            (!$tournament->group_count || !$tournament->group_advance_count)
        ) {
            abort(400);
        }

        if ($tournament->mode === 'group_ko') {

            app(\App\Services\GroupGenerator::class)
                ->generate($tournament, $tournament->group_count);

            $tournament->update([
                'status' => 'group_running'
            ]);

            return redirect()->route('tournaments.show', $tournament);
        }

        // normales KO
        if ($tournament->mode === 'ko') {

            app(\App\Services\KnockoutGenerator::class)
                ->generate($tournament);

            $tournament->update([
                'status' => 'ko_running'
            ]);
        }

        return redirect()->route('tournaments.show', $tournament);
    }

    public function draw(Tournament $tournament)
    {
        if ($tournament->user_id !== auth()->id()) {
            abort(403);
        }

        if ($tournament->status !== 'draft') {
            abort(400);
        }

        $players = $tournament->players()->inRandomOrder()->get();

        foreach ($players as $index => $player) {
            $player->update([
                'seed' => $index + 1
            ]);
        }

        return back();
    }

    public function updateScore(Request $request, Game $game)
    {
        $tournament = $game->tournament;

        // Sicherheitscheck
        if ($tournament->user_id !== auth()->id()) {
            abort(403);
        }

        // Validierung
        $validated = $request->validate([
            'player1_score' => 'required|integer|min:0',
            'player2_score' => 'required|integer|min:0',
        ]);

        // Falls Spiel bereits entschieden → nichts mehr ändern
        if ($game->winner_id) {
            return back();
        }

        // Scores speichern
        $game->update([
            'player1_score' => $validated['player1_score'],
            'player2_score' => $validated['player2_score'],
        ]);

        // Best-of Berechnung
        $firstTo = ceil($game->best_of / 2);

        // Gewinner automatisch bestimmen
        if ($game->player1_score >= $firstTo) {
            app(\App\Services\TournamentEngine::class)
                ->handleWin($game, $game->player1_id);
        }

        if ($game->player2_score >= $firstTo) {
            app(\App\Services\TournamentEngine::class)
                ->handleWin($game, $game->player2_id);
        }

        return back();
    }

    private function advanceWinner(Game $game, $winnerId)
    {
        $tournament = $game->tournament;

        $game->update(['winner_id' => $winnerId]);

        $totalPlayers = $tournament->players()->count();
        $totalRounds = log($totalPlayers, 2);

        $gamesInRound = Game::where('tournament_id', $tournament->id)
            ->where('round', $game->round)
            ->count();

        if ($gamesInRound === 1 && $game->round == $totalRounds) {
            $tournament->update(['status' => 'finished']);
            return;
        }

        $nextRound = $game->round + 1;
        $nextPosition = ceil($game->position / 2);

        $nextGame = Game::firstOrCreate([
            'tournament_id' => $tournament->id,
            'round' => $nextRound,
            'position' => $nextPosition,
        ]);

        if ($game->position % 2 === 1) {
            $nextGame->update(['player1_id' => $winnerId]);
        } else {
            $nextGame->update(['player2_id' => $winnerId]);
        }

        $isSemifinal = ($game->round == $totalRounds - 1);

        if ($tournament->has_third_place && $isSemifinal) {

            $semifinalLosers = Game::where('tournament_id', $tournament->id)
                ->where('round', $game->round)
                ->whereNotNull('winner_id')
                ->get()
                ->map(function ($g) {
                    return $g->player1_id === $g->winner_id
                        ? $g->player2_id
                        : $g->player1_id;
                });

            if ($semifinalLosers->count() === 2) {

                Game::firstOrCreate([
                    'tournament_id' => $tournament->id,
                    'round' => $totalRounds + 1,
                    'position' => 1,
                ], [
                    'player1_id' => $semifinalLosers[0],
                    'player2_id' => $semifinalLosers[1],
                    'best_of' => $game->best_of,
                ]);
            }
        }
    }
    public function updateRoundBestOf(Request $request, Tournament $tournament, $round)
    {
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

        if ($tournament->user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'best_of' => 'required|integer|min:1',
        ]);

        // Keine Änderung erlauben wenn Spiel bereits entschieden
        $hasFinishedGame = Game::where('tournament_id', $tournament->id)
            ->where('round', $round)
            ->whereNotNull('winner_id')
            ->exists();

        if ($hasFinishedGame) {
            return back()->with('error', 'Best-of kann nicht mehr geändert werden.');
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
        // Alte KO-Spiele löschen
        Game::where('tournament_id', $tournament->id)
            ->where('round', '>', 0)
            ->delete();
        if ($tournament->user_id !== auth()->id()) {
            abort(403);
        }

        if ($tournament->status !== 'group_running') {
            abort(400);
        }

        $qualified = collect();

        foreach ($tournament->groups as $group) {

            $table = app(\App\Services\GroupTableCalculator::class)
                ->calculate($group);

            $topPlayers = collect($table)
                ->take($tournament->group_advance_count)
                ->pluck('player');

            $qualified = $qualified->merge($topPlayers);
        }

        if ($qualified->count() < 2) {
            abort(400);
        }

        // group_id entfernen (optional sauberer)
        foreach ($qualified as $player) {
            $player->update(['group_id' => null]);
        }

        // KO generieren mit qualifizierten Spielern
        app(\App\Services\KnockoutGenerator::class)
            ->generateFromCollection($tournament, $qualified);

        $tournament->update([
            'status' => 'ko_running'
        ]);

        return redirect()->route('tournaments.show', $tournament);
    }

    public function generateFromCollection(Tournament $tournament, $players)
    {
        $players = $players->values();

        $round = 1;
        $position = 1;

        for ($i = 0; $i < $players->count(); $i += 2) {

            Game::create([
                'tournament_id' => $tournament->id,
                'player1_id' => $players[$i]->id,
                'player2_id' => $players[$i + 1]->id ?? null,
                'round' => $round,
                'position' => $position++,
                'best_of' => 3,
            ]);
        }
    }
}
