@php
    /*
    {{-- 🔹 Steuert, ob das KO-Bracket angezeigt werden soll --}}
    {{-- Sichtbar bei: KO läuft, Turnier fertig, oder Gruppenphase --}}
    */
    $showBracket =
        $tournament->status === 'ko_running' ||
        $tournament->status === 'finished' ||
        $tournament->status === 'group_running';
@endphp

@if ($showBracket)

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
            // Erste Runde (enthält alle Startspiele)
            $firstRound = $mainRounds->first();

            // Anzahl Spiele in Runde 1
            $gamesFirstRound = $firstRound ? $firstRound->count() : 0;

            // Teilnehmerzahl berechnen (2 Spieler pro Spiel)
            $totalPlayers = $gamesFirstRound * 2;

            // Sonderfall: nur ein Spiel → Finale
            if ($totalPlayers <= 1) {
                return 'Finale';
            }

            // Anzahl Runden im KO-System (log2)
            $totalRounds = (int) log($totalPlayers, 2);

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

        // 🔹 Höhe des gesamten Brackets berechnen
        $totalFirstRound = $mainRounds[1]->count();
        $bracketHeight = $totalFirstRound * ($matchHeight + $verticalSpacing);

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

        foreach ($mainRounds as $round => $roundGames) {
            // 🔹 Spiele korrekt sortieren
            $roundGames = $roundGames->sortBy('position')->values();

            // 🔹 X-Position basiert auf Runde
            $roundIndex = $round - 1;

            foreach ($roundGames as $i => $game) {
                /*
                |--------------------------------------------------------------------------
                | 📍 Y-POSITION BERECHNEN
                |--------------------------------------------------------------------------
                */

                if ($round == 1) {
                    // Erste Runde: linear von oben nach unten
                    $y = $i * ($matchHeight + $verticalSpacing) + $headerOffset;
                } else {
                    // Folge-Runden: zwischen zwei Vorgängern zentrieren
                    $prev1 = $positions[$round - 1][$i * 2] ?? 0;
                    $prev2 = $positions[$round - 1][$i * 2 + 1] ?? 0;

                    // Mittelpunkt bestimmen
                    $y = ($prev1 + $prev2) / 2;
                }

                // Position speichern
                $positions[$round][$i] = $y;

                // Finale merken (für Platz-3-Spiel)
                if ($round === $finalRound) {
                    $finalY = $y;
                }

                /*
                |--------------------------------------------------------------------------
                | 🔗 SVG-LINIEN ZEICHNEN
                |--------------------------------------------------------------------------
                */

                if ($round > 1) {
                    $xStart = ($roundIndex - 1) * $roundWidth + 200;
                    $xEnd = $roundIndex * $roundWidth;

                    // Vorgänger-Spiele
                    $prevGames = $mainRounds[$round - 1]->sortBy('position')->values();
                    $prevGame1 = $prevGames[$i * 2] ?? null;
                    $prevGame2 = $prevGames[$i * 2 + 1] ?? null;

                    // Y-Positionen der Vorgänger
                    $prev1 = $positions[$round - 1][$i * 2] ?? 0;
                    $prev2 = $positions[$round - 1][$i * 2 + 1] ?? 0;

                    // Prüfen, ob Champion durchläuft (für grüne Linien)
                    $topChampion = $championId && $prevGame1 && $prevGame1->winner_id === $championId;
                    $bottomChampion = $championId && $prevGame2 && $prevGame2->winner_id === $championId;

                    // Mittelpunkt zwischen beiden Spielen
                    $mid = ($prev1 + $prev2) / 2;

                    // Horizontale Linien links
                    $svgLines[] = [
                        'x1' => $xStart,
                        'y1' => $prev1,
                        'x2' => $xStart + 30,
                        'y2' => $prev1,
                        'color' => $topChampion ? '#22c55e' : '#4b5563',
                    ];
                    $svgLines[] = [
                        'x1' => $xStart,
                        'y1' => $prev2,
                        'x2' => $xStart + 30,
                        'y2' => $prev2,
                        'color' => $bottomChampion ? '#22c55e' : '#4b5563',
                    ];

                    // Vertikale Verbindung
                    $svgLines[] = [
                        'x1' => $xStart + 30,
                        'y1' => $prev1,
                        'x2' => $xStart + 30,
                        'y2' => $mid,
                        'color' => $topChampion ? '#22c55e' : '#4b5563',
                    ];
                    $svgLines[] = [
                        'x1' => $xStart + 30,
                        'y1' => $mid,
                        'x2' => $xStart + 30,
                        'y2' => $prev2,
                        'color' => $bottomChampion ? '#22c55e' : '#4b5563',
                    ];

                    // Verbindung zur aktuellen Runde
                    $currentChampion = $championId && $game->winner_id === $championId;

                    $svgLines[] = [
                        'x1' => $xStart + 30,
                        'y1' => $y,
                        'x2' => $xEnd,
                        'y2' => $y,
                        'color' => $currentChampion ? '#22c55e' : '#4b5563',
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
                        <form method="POST"
                            action="{{ route('tournaments.updateRoundBestOf', [$tournament, $round]) }}">
                            @csrf
                            @method('PATCH')

                            <select name="best_of"
                                onchange="updateBestOf(this, {{ $tournament->id }}, {{ $round }})"
                                class="mt-1 w-20 text-xs font-semibold
               bg-gray-800 text-emerald-400
               border border-gray-700
               rounded-md px-2 py-1
               focus:outline-none focus:ring-2 focus:ring-emerald-500
               transition">
                                @foreach ([1, 3, 5, 7, 9] as $value)
                                    <option value="{{ $value }}"
                                        {{ $currentBestOf == $value ? 'selected' : '' }}>
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
                            $y = $positions[$round][$i];

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

                            {{-- Kennzeichnung: es ist Platz 3 --}}
                            <input type="hidden" name="is_third_place" value="1">

                            @php
                                // aktueller Best-of Wert
                                $thirdBestOf = $thirdPlaceGame?->best_of;

                                // bereits gespielt?
                                $hasResults = $thirdPlaceGame && $thirdPlaceGame->winner_id;
                            @endphp

                            <select name="best_of"
                                onchange="updateBestOf(this, {{ $tournament->id }}, {{ $round }})""
                                class="mt-1 w-20 text-xs font-semibold
                   bg-gray-800 text-amber-400
                   border border-gray-700
                   rounded-md px-2 py-1
                   focus:outline-none focus:ring-2 focus:ring-amber-500
                   transition">

                                @foreach ([1, 3, 5, 7, 9] as $value)
                                    <option value="{{ $value }}"
                                        {{ $thirdBestOf == $value ? 'selected' : '' }}>
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

@endif
