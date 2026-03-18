{{-- 
|--------------------------------------------------------------------------
| 🟢 GROUP GAMES WRAPPER (FINAL)
|--------------------------------------------------------------------------
|
| Diese Datei rendert ALLE Spiele einer Gruppe.
|
| WICHTIG:
| - Enthält KEINE Spiellogik mehr
| - Kein Zugriff auf $game außerhalb der Schleife!
| - Delegiert jedes Spiel an:
|   → _group_game_single.blade.php
|
| Vorteile:
| - Saubere Trennung (Single Responsibility)
| - AJAX reload einzelner Spiele möglich (reloadGame)
| - Kein Undefined $game Fehler mehr 🚀
|
--}}

<div data-group-games="{{ $group->id }}">

    @foreach ($group->games->sortBy(fn($g) => $g->winner_id ? 1 : 0)->values() as $game)
        {{-- 
        |--------------------------------------------------------------------------
        | 🎯 EINZELNES SPIEL
        |--------------------------------------------------------------------------
        |
        | Übergibt:
        | - $game → aktuelles Spiel
        | - $tournament → benötigt für Status (group_running etc.)
        |
        --}}
        @include('tournaments.partials._group_game_single', [
            'game' => $game,
            'tournament' => $tournament,
            'group' => $group,
        ])
    @endforeach

</div>
