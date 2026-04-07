<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Services\Knockout\KnockoutAdvancer;
use App\Services\Knockout\KnockoutProgressionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TournamentGameController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Spiel Score speichern
    |--------------------------------------------------------------------------
    |
    | Wird aufgerufen wenn ein Spiel gespeichert wird
    |
    | Ablauf:
    |
    | 1. Validierung
    | 2. Ergebnis speichern
    | 3. Gewinner bestimmen
    | 4. KO-Logik ausführen (Advancer)
    | 5. Reload IDs zurückgeben
    |
    */

    public function updateScore(Request $request, Game $game)
    {
        $tournament = $game->tournament;

        /*
        |--------------------------------------------------------------------------
        | Prüfen ob Spiel bereits entschieden
        |--------------------------------------------------------------------------
        */

        if ($game->winner_id) {
            return response()->json([
                'success' => true,
                'game_id' => $game->id,
                'group_id' => $game->group_id,
            ]);
        }

        if (! $game->player1_id || ! $game->player2_id) {
            return response()->json([
                'success' => false,
                'error' => 'Ergebnisse können erst eingetragen werden, wenn beide Teilnehmer feststehen.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Validierung
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([
            'player1_score' => 'required|integer|min:0',
            'player2_score' => 'required|integer|min:0',
            'winning_rest' => 'nullable|integer|min:0|max:501',
        ]);

        $player1Score = $validated['player1_score'];
        $player2Score = $validated['player2_score'];
        $winningRest = $validated['winning_rest'] ?? null;

        /*
        |--------------------------------------------------------------------------
        | Ergebnis validieren
        |--------------------------------------------------------------------------
        */

        try {
            $game->validateResult(
                $player1Score,
                $player2Score,
                $winningRest
            );
        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Scores speichern
        |--------------------------------------------------------------------------
        */

        $game->update([
            'player1_score' => $player1Score,
            'player2_score' => $player2Score,
            'winning_rest' => $winningRest,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Gewinner bestimmen
        |--------------------------------------------------------------------------
        */

        $firstTo = ceil($game->best_of / 2);

        $winnerId = null;

        if ($player1Score >= $firstTo) {
            $winnerId = $game->player1_id;
        }

        if ($player2Score >= $firstTo) {
            $winnerId = $game->player2_id;
        }

        /*
        |--------------------------------------------------------------------------
        | Gewinner verarbeiten (KO Logik)
        |--------------------------------------------------------------------------
        */

        $reload = [];
        $fullReload = false;

        if ($winnerId) {
            $result = app(KnockoutAdvancer::class)
                ->handleWin($game, $winnerId);

            $reload = $result['reload'] ?? [];
            $fullReload = $result['fullReload'] ?? false;
        }

        /*
        |--------------------------------------------------------------------------
        | Spiel neu laden
        |--------------------------------------------------------------------------
        */

        $game->refresh();

        /*
        |--------------------------------------------------------------------------
        | Reload IDs vorbereiten
        |--------------------------------------------------------------------------
        */

        $reload = array_unique(array_merge(
            [$game->id],
            $reload ?? []
        ));

        /*
        |--------------------------------------------------------------------------
        | JSON Response
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,
            'game_id' => $game->id,
            'group_id' => $game->group_id,
            'reload' => $reload,
            'fullReload' => $fullReload,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Spiel zurücksetzen
    |--------------------------------------------------------------------------
    |
    | Wird aufgerufen wenn ein Spiel gelöscht wird
    |
    | Ablauf:
    |
    | 1. Spiel reset
    | 2. Folgespiele resetten (rekursiv)
    | 3. Reload IDs zurückgeben
    |
    */

    public function resetGame(Game $game)
    {
        if (! $game->canBeReset()) {
            abort(403);
        }
        $reloadIds = [];

        DB::transaction(function () use ($game, &$reloadIds) {

            /*
            |--------------------------------------------------------------------------
            | Spiel reset
            |--------------------------------------------------------------------------
            */

            $game->update([
                'player1_score' => null,
                'player2_score' => null,
                'winner_id' => null,
                'winning_rest' => null,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Folgespiele resetten
            |--------------------------------------------------------------------------
            */

            $service = new KnockoutProgressionService;

            $reloadIds = $service->clearFromGame($game);
        });

        /*
        |--------------------------------------------------------------------------
        | JSON Response
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,
            'game_id' => $game->id,
            'group_id' => $game->group_id,
            'reload' => array_unique(array_merge([$game->id], $reloadIds)),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Spiel Reload (AJAX)
    |--------------------------------------------------------------------------
    */
    public function reloadGame(Game $game)
    {
        return view('tournaments.partials._group_game_single', [
            'game' => $game,
        ])->render();
    }

    /*
    |--------------------------------------------------------------------------
    | Nächstes Spiel finden
    |--------------------------------------------------------------------------
    */
    public function nextGame(Game $game)
    {
        $next = Game::where('previous_game_id', $game->id)->first();

        return response()->json([
            'next_game_id' => $next?->id,
        ]);
    }
}
