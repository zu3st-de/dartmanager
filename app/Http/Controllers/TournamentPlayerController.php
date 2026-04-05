<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TournamentPlayerController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Spieler hinzufügen
    |--------------------------------------------------------------------------
    */

    public function addPlayer(Request $request, Tournament $tournament)
    {
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
            return response()->json($player);
        }

        return back();
    }


    /*
    |--------------------------------------------------------------------------
    | Bulk Spieler
    |--------------------------------------------------------------------------
    */

    public function bulkPlayers(Request $request, Tournament $tournament)
    {
        if ($tournament->status !== 'draft') {
            return back()->with('error', 'Nur im Draft möglich');
        }

        $request->validate([
            'bulk_players' => 'required|string'
        ]);

        $lines = preg_split('/\r\n|\r|\n/', $request->bulk_players);

        foreach ($lines as $line) {

            $name = trim($line);

            if (!$name) continue;

            $tournament->players()->firstOrCreate([
                'name' => $name
            ]);
        }

        return back()->with('success', 'Spieler importiert');
    }


    /*
    |--------------------------------------------------------------------------
    | Auslosung
    |--------------------------------------------------------------------------
    */

    public function draw(Tournament $tournament)
    {
        if ($tournament->status !== 'draft') {
            return back()->with('error', 'Nur im Draft möglich');
        }

        DB::transaction(function () use ($tournament) {

            $players = $tournament->players()
                ->inRandomOrder()
                ->lockForUpdate()
                ->get();

            foreach ($players as $i => $player) {
                $player->update(['seed' => $i + 1]);
            }
        });

        return back()->with('success', 'Ausgelost');
    }
}
