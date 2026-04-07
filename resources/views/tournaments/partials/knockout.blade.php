@php

    /*
        |--------------------------------------------------------------------------
        | 🔤 RUNDEN-NAMEN
        |--------------------------------------------------------------------------
        |
        | Wandelt interne Runden (1,2,3,...) in lesbare Namen:
        | Finale, Halbfinale, Viertelfinale usw.
        |
        */
    function roundName($round, $mainRounds)
    {
        $baseRound = $mainRounds
            ->sortByDesc(fn($games) => $games->count())
            ->keys()
            ->first() ?? 1;

        if ($round < $baseRound) {
            return $baseRound - $round === 1
                ? 'Vorrunde'
                : 'Vorrunde ' . ($baseRound - $round);
        }

        $totalRounds = $mainRounds->keys()->max() ?? 1;

        if ($totalRounds <= 1) {
            return 'Finale';
        }

        // Abstand zum Finale bestimmen
        $roundsLeft = $totalRounds - $round + 1;

        // Mapping auf bekannte Begriffe
        return match ($roundsLeft) {
            1 => 'Finale',
            2 => 'Halbfinale',
            3 => 'Viertelfinale',
            4 => 'Achtelfinale',
            5 => 'Sechzehntelfinale',
            6 => 'Zweiunddreißigstelfinale',
            default => 'Runde ' . $round,
        };
    }

    /*
        |--------------------------------------------------------------------------
        | 📦 DATEN AUFBEREITUNG
        |--------------------------------------------------------------------------
        */

    // 🔹 Nur KO-Spiele (Gruppenphase wird ausgeschlossen)
    $games = $tournament->games->whereNull('group_id');

    // 🔹 Hauptbaum (ohne Spiel um Platz 3)
    $mainRounds = $games->where('is_third_place', false)->groupBy('round')->sortKeys();

    // 🔹 Spiel um Platz 3 separat
    $thirdPlaceGame = $games->firstWhere('is_third_place', true);

    /*
        |--------------------------------------------------------------------------
        | 📐 LAYOUT PARAMETER
        |--------------------------------------------------------------------------
        |
        | Diese Werte bestimmen die gesamte Darstellung des Brackets
        |
        */
    $headerOffset = 130; // Abstand oben für Rundenüberschriften
    $roundWidth = 260; // Abstand zwischen Runden (X)
    $matchHeight = 160; // Höhe eines Spiels
    $verticalSpacing = 40; // Abstand zwischen Spielen

    /*
        |--------------------------------------------------------------------------
        | 🏆 FINALE & CHAMPION
        |--------------------------------------------------------------------------
        */

    // 🔹 Letzte Runde bestimmen
    $finalRound = $mainRounds->keys()->max();

    // 🔹 Finalspiel laden
    $finalGame = $mainRounds[$finalRound]->first();

    // 🔹 Gewinner-ID (für Hervorhebung im Bracket)
    $championId = $finalGame && $finalGame->winner_id ? $finalGame->winner_id : null;

    /*
        |--------------------------------------------------------------------------
        | 📍 POSITIONEN & SVG-LINIEN
        |--------------------------------------------------------------------------
        |
        | $positions → speichert Y-Positionen der Spiele
        | $svgLines → enthält alle Linien für das SVG
        |
        */
    $positions = [];
    $svgLines = [];
    $finalY = null;
    $gameLookup = [];
    $yStep = $matchHeight + $verticalSpacing;

    foreach ($mainRounds as $round => $roundGames) {
        foreach ($roundGames->sortBy('position')->values() as $game) {
            $gameLookup[$round][$game->position] = $game;
        }
    }

    $baseRound = $mainRounds
        ->sortByDesc(fn($games) => $games->count())
        ->keys()
        ->first() ?? 1;

    // 🔹 Höhe des gesamten Brackets berechnen
    $baseRoundCount = $mainRounds[$baseRound]->count();
    $bracketHeight = max($baseRoundCount, 1) * $yStep;

    /*
        |--------------------------------------------------------------------------
        | 🔁 CORE: POSITIONEN & VERBINDUNGEN
        |--------------------------------------------------------------------------
        |
        | Hier passiert:
        | - Berechnung der Spiel-Positionen
        | - Zeichnen der Verbindungslinien
        |
        */

    if (isset($mainRounds[$baseRound])) {
        foreach ($mainRounds[$baseRound]->sortBy('position')->values() as $i => $game) {
            $positions[$baseRound][$game->position] = $headerOffset + $i * $yStep;
        }
    }

    for ($round = $baseRound + 1; $round <= $finalRound; $round++) {
        $roundGames = $mainRounds[$round]->sortBy('position')->values();

        foreach ($roundGames as $i => $game) {
            $sourcePositions = collect([
                $game->player1_source,
                $game->player2_source,
            ])->filter()
                ->map(function ($source) use ($positions, $round) {
                    if (!preg_match('/^W(\d+)$/', $source, $matches)) {
                        return null;
                    }

                    return $positions[$round - 1][(int) $matches[1]] ?? null;
                })
                ->filter()
                ->values();

            if ($sourcePositions->isNotEmpty()) {
                $y = $sourcePositions->avg();
            } else {
                $y = $headerOffset + $i * $yStep;
            }

            $positions[$round][$game->position] = $y;
        }
    }

    for ($round = $baseRound - 1; $round >= 1; $round--) {
        $roundGames = $mainRounds[$round]->sortBy('position')->values();

        foreach ($roundGames as $i => $game) {
            $targetRoundGames = $mainRounds[$round + 1]->sortBy('position')->values();

            $targetGame = $targetRoundGames->first(function ($candidate) use ($game) {
                return $candidate->player1_source === 'W' . $game->position
                    || $candidate->player2_source === 'W' . $game->position;
            });

            if ($targetGame) {
                $y = $positions[$round + 1][$targetGame->position] ?? ($headerOffset + $i * $yStep);
            } else {
                $y = $headerOffset + $i * $yStep;
            }

            $positions[$round][$game->position] = $y;
        }
    }

    foreach ($mainRounds as $round => $roundGames) {
        foreach ($roundGames->sortBy('position')->values() as $game) {
            $y = $positions[$round][$game->position] ?? $headerOffset;

            if ($round === $finalRound) {
                $finalY = $y;
            }

            if ($round <= 1) {
                continue;
            }

            $roundIndex = $round - 1;
            $xStart = ($roundIndex - 1) * $roundWidth + 200;
            $xEnd = $roundIndex * $roundWidth;

            foreach (['player1_source', 'player2_source'] as $sourceField) {
                $source = $game->{$sourceField};

                if (!$source || !preg_match('/^W(\d+)$/', $source, $matches)) {
                    continue;
                }

                $sourcePosition = (int) $matches[1];
                $prevGame = $gameLookup[$round - 1][$sourcePosition] ?? null;
                $prevY = $positions[$round - 1][$sourcePosition] ?? null;

                if (!$prevGame || $prevY === null) {
                    continue;
                }

                $isChampionPath = $championId && $prevGame->winner_id === $championId;
                $color = $isChampionPath ? '#22c55e' : '#4b5563';
                $midX = $xStart + 30;

                $svgLines[] = [
                    'x1' => $xStart,
                    'y1' => $prevY,
                    'x2' => $midX,
                    'y2' => $prevY,
                    'color' => $color,
                ];
                $svgLines[] = [
                    'x1' => $midX,
                    'y1' => $prevY,
                    'x2' => $midX,
                    'y2' => $y,
                    'color' => $color,
                ];
                $svgLines[] = [
                    'x1' => $midX,
                    'y1' => $y,
                    'x2' => $xEnd,
                    'y2' => $y,
                    'color' => $color,
                ];
            }
        }
    }
@endphp
{{-- 🔹 Hauptcontainer für das KO-Bracket --}}
<div class="bg-gray-900 border border-gray-800 rounded-xl p-6 shadow-lg overflow-x-auto">

    {{-- Titel --}}
    <h2 class="text-lg font-semibold mb-6 text-white">
        KO-Bracket
    </h2>

    {{-- 🔹 Wrapper zum Zentrieren --}}
    <div class="flex justify-center">

        {{-- 🔹 Bracket-Container mit dynamischer Größe --}}
        <div class="relative"
            style="min-width: {{ count($mainRounds) * $roundWidth }}px;
                    min-height: {{ $bracketHeight + $headerOffset + 200 }}px;">

            {{-- 🔗 SVG-Linien zwischen den Spielen --}}
            <svg class="absolute top-0 left-0 w-full h-full pointer-events-none">

                {{-- Jede Linie wird einzeln gezeichnet --}}
                @foreach ($svgLines as $line)
                    <line x1="{{ $line['x1'] }}" y1="{{ $line['y1'] }}" x2="{{ $line['x2'] }}"
                        y2="{{ $line['y2'] }}" stroke="{{ $line['color'] }}"
                        stroke-width="{{ $line['color'] === '#22c55e' ? 3 : 2 }}" />
                @endforeach

            </svg>

            {{-- 🔹 RUNDEN DURCHLAUFEN --}}
            @foreach ($mainRounds as $round => $roundGames)
                @php
                    // Spiele sortieren (wichtig für richtige Darstellung)
                    $roundGames = $roundGames->sortBy('position')->values();

                    // Best-of Wert der Runde (für Dropdown)
                    $currentBestOf = $roundGames->first()?->best_of;

                    // X-Position der Runde
                    $roundIndex = $round - 1;
                    $x = $roundIndex * $roundWidth;
                @endphp

                {{-- 🔤 RUNDENÜBERSCHRIFT --}}
                <div class="absolute text-center text-gray-400 text-sm font-semibold"
                    style="left: {{ $x }}px;
                top: 0px;
                width: 240px;">

                    {{-- Anzeigename der Runde --}}
                    {{ roundName($round, $mainRounds) }}

                    {{-- 🔹 Best-of Auswahl für diese Runde --}}
                    <form method="POST" action="{{ route('tournaments.updateRoundBestOf', [$tournament, $round]) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="round" value="{{ $round }}">

                        <select name="best_of"
                            onchange="this.form.requestSubmit()"
                            class="mt-1 w-20 text-xs font-semibold
               bg-gray-800 text-emerald-400
               border border-gray-700
               rounded-md px-2 py-1
               focus:outline-none focus:ring-2 focus:ring-emerald-500
               transition">
                            @foreach ([1, 3, 5, 7, 9] as $value)
                                <option value="{{ $value }}" {{ $currentBestOf == $value ? 'selected' : '' }}>
                                    Bo{{ $value }}
                                </option>
                            @endforeach
                        </select>
                    </form>

                </div>

                {{-- 🔹 MATCHES DER RUNDE --}}
                @foreach ($roundGames as $i => $game)
                    @php
                        // Y-Position aus vorher berechneten Daten
                        $y = $positions[$round][$game->position];

                        // X-Position der Runde
                        $x = $roundIndex * $roundWidth;
                    @endphp

                    {{-- 🔹 Einzelnes Spiel --}}
                    <div class="absolute"
                        style="left: {{ $x }}px;
                            top: {{ $y - $matchHeight / 2 }}px;
                            width: 240px;">

                        {{-- Game-Komponente (Partial) --}}
                        @include('tournaments.partials._ko_game', [
                            'game' => $game,
                            'tournament' => $tournament,
                            'maxRound' => $finalRound,
                        ])

                    </div>
                @endforeach
            @endforeach

            {{-- 🥉 SPIEL UM PLATZ 3 --}}
            @if ($thirdPlaceGame && $finalY !== null)

                @php
                    // Position unterhalb des Finales
                    $thirdY = $finalY + $matchHeight + 80;

                    // Rechts neben Finale platzieren
                    $thirdX = ($finalRound - 1) * $roundWidth + 20;
                @endphp

                {{-- 🔤 Überschrift Platz 3 --}}
                <div class="absolute text-center text-amber-400 text-sm font-semibold"
                    style="left: {{ $thirdX }}px;
                top: {{ $thirdY - $matchHeight + 30 }}px;
                width: 240px;">

                    Spiel um Platz 3

                    {{-- 🔹 Best-of Auswahl für Platz 3 --}}
                    <form method="POST"
                        action="{{ route('tournaments.updateRoundBestOf', [$tournament, $finalRound]) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="round" value="{{ $finalRound }}">

                        {{-- Kennzeichnung: es ist Platz 3 --}}
                        <input type="hidden" name="is_third_place" value="1">

                        @php
                            // aktueller Best-of Wert
                            $thirdBestOf = $thirdPlaceGame?->best_of;

                            // bereits gespielt?
                            $hasResults = $thirdPlaceGame && $thirdPlaceGame->winner_id;
                        @endphp

                        <select name="best_of"
                            onchange="this.form.requestSubmit()"
                            class="mt-1 w-20 text-xs font-semibold
                   bg-gray-800 text-amber-400
                   border border-gray-700
                   rounded-md px-2 py-1
                   focus:outline-none focus:ring-2 focus:ring-amber-500
                   transition">

                            @foreach ([1, 3, 5, 7, 9] as $value)
                                <option value="{{ $value }}" {{ $thirdBestOf == $value ? 'selected' : '' }}>
                                    Bo{{ $value }}
                                </option>
                            @endforeach
                        </select>
                    </form>

                </div>

                {{-- 🔹 Platz-3-Spiel --}}
                <div class="absolute"
                    style="left: {{ $thirdX }}px;
                top: {{ $thirdY - $matchHeight / 2 }}px;
                width: 240px;">

                    @include('tournaments.partials._ko_game', [
                        'game' => $thirdPlaceGame,
                        'tournament' => $tournament,
                        'maxRound' => $finalRound,
                    ])

                </div>

            @endif

        </div>
    </div>
</div>
