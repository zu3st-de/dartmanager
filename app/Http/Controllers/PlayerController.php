<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Player;

class PlayerController extends Controller
{
    public function update(Request $request, Player $player)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        // Optional: Owner-Check
        if ($player->tournament->user_id !== auth()->id()) {
            abort(403);
        }

        $player->update([
            'name' => $request->name,
        ]);

        return back()->with('success', 'Spielername aktualisiert.');
    }
    public function destroy(Player $player)
    {
        $tournament = $player->tournament;

        if ($tournament->status !== 'draft') {
            return back()->with('error', 'Spieler können nach Turnierstart nicht gelöscht werden.');
        }

        if ($tournament->user_id !== auth()->id()) {
            abort(403);
        }

        $player->delete();

        return back()->with('success', 'Spieler gelöscht.');
    }
    public function reset(Request $request, Tournament $tournament)
    {
        // Nur Owner
        if ($tournament->user_id !== auth()->id()) {
            abort(403);
        }

        // Name muss exakt passen
        $request->validate([
            'confirm_name' => ['required', 'in:' . $tournament->name],
        ]);

        DB::transaction(function () use ($tournament) {

            // Alle Spiele löschen
            $tournament->games()->delete();

            // Optional: Gruppen löschen
            $tournament->groups()->delete();

            // Status zurücksetzen
            $tournament->update([
                'status' => 'draft'
            ]);
        });

        return redirect()
            ->route('tournaments.show', $tournament)
            ->with('success', 'Turnier wurde zurückgesetzt.');
    }
}
