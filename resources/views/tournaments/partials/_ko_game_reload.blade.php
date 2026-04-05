    @include('tournaments.partials._ko_game_inner', [
        'game' => $game,
        'tournament' => $tournament,
        'maxRound' => $maxRound ?? null,
    ])
