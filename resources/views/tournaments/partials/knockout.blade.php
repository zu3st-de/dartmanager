@if($tournament->status === 'ko_running' || $tournament->status === 'finished')

@php

function roundName($round, $mainRounds)
{
// Erste Runde ermitteln
$firstRound = $mainRounds->first();
$gamesFirstRound = $firstRound ? $firstRound->count() : 0;

// Teilnehmerzahl berechnen
$totalPlayers = $gamesFirstRound * 2;

if ($totalPlayers <= 1) {
    return 'Finale' ;
    }

    // Gesamtanzahl Runden im Baum
    $totalRounds=(int) log($totalPlayers, 2);

    // Wie viele Runden sind noch übrig?
    $roundsLeft=$totalRounds - $round + 1;

    return match ($roundsLeft) {
    1=> 'Finale',
    2 => 'Halbfinale',
    3 => 'Viertelfinale',
    4 => 'Achtelfinale',
    5 => 'Sechzehntelfinale',
    6 => 'Zweiunddreißigstelfinale',
    default => 'Runde ' . $round,
    };
    }

    $games = $tournament->games->whereNull('group_id');

    $mainRounds = $games
    ->where('is_third_place', false)
    ->groupBy('round')
    ->sortKeys();

    $thirdPlaceGame = $games->firstWhere('is_third_place', true);

    $headerOffset = 130;
    $roundWidth = 260;
    $matchHeight = 160;
    $verticalSpacing = 40;

    $finalRound = $mainRounds->keys()->max();
    $finalGame = $mainRounds[$finalRound]->first();
    $championId = ($finalGame && $finalGame->winner_id) ? $finalGame->winner_id : null;

    $positions = [];
    $svgLines = [];
    $finalY = null;

    $totalFirstRound = $mainRounds[1]->count();
    $bracketHeight = $totalFirstRound * ($matchHeight + $verticalSpacing);

    /*
    |--------------------------------------------------------------------------
    | POSITION & LINES
    |--------------------------------------------------------------------------
    */

    foreach ($mainRounds as $round => $roundGames) {

    $roundGames = $roundGames->sortBy('position')->values();
    $roundIndex = $round - 1;

    foreach ($roundGames as $i => $game) {

    if ($round == 1) {
    $y = $i * ($matchHeight + $verticalSpacing) + $headerOffset;
    } else {
    $prev1 = $positions[$round-1][$i*2] ?? 0;
    $prev2 = $positions[$round-1][$i*2+1] ?? 0;
    $y = ($prev1 + $prev2) / 2;
    }

    $positions[$round][$i] = $y;

    if ($round === $finalRound) {
    $finalY = $y;
    }

    if ($round > 1) {

    $xStart = ($roundIndex - 1) * $roundWidth + 200;
    $xEnd = $roundIndex * $roundWidth;

    $prevGames = $mainRounds[$round-1]->sortBy('position')->values();
    $prevGame1 = $prevGames[$i*2] ?? null;
    $prevGame2 = $prevGames[$i*2+1] ?? null;

    $prev1 = $positions[$round-1][$i*2] ?? 0;
    $prev2 = $positions[$round-1][$i*2+1] ?? 0;

    $topChampion = $championId && $prevGame1 && $prevGame1->winner_id === $championId;
    $bottomChampion = $championId && $prevGame2 && $prevGame2->winner_id === $championId;

    $mid = ($prev1 + $prev2) / 2;

    $svgLines[] = ['x1'=>$xStart,'y1'=>$prev1,'x2'=>$xStart+30,'y2'=>$prev1,'color'=>$topChampion ? '#22c55e' : '#4b5563'];
    $svgLines[] = ['x1'=>$xStart,'y1'=>$prev2,'x2'=>$xStart+30,'y2'=>$prev2,'color'=>$bottomChampion ? '#22c55e' : '#4b5563'];

    $svgLines[] = ['x1'=>$xStart+30,'y1'=>$prev1,'x2'=>$xStart+30,'y2'=>$mid,'color'=>$topChampion ? '#22c55e' : '#4b5563'];
    $svgLines[] = ['x1'=>$xStart+30,'y1'=>$mid,'x2'=>$xStart+30,'y2'=>$prev2,'color'=>$bottomChampion ? '#22c55e' : '#4b5563'];

    $currentChampion = $championId && $game->winner_id === $championId;

    $svgLines[] = ['x1'=>$xStart+30,'y1'=>$y,'x2'=>$xEnd,'y2'=>$y,'color'=>$currentChampion ? '#22c55e' : '#4b5563'];
    }
    }
    }
    @endphp


    <div class="bg-gray-900 border border-gray-800 rounded-xl p-6 shadow-lg overflow-x-auto">
        <h2 class="text-lg font-semibold mb-6 text-white">
            KO-Bracket
        </h2>
        {{-- 🔥 Zentrierungs-Wrapper --}}
        <div class="flex justify-center">

            <div class="relative"
                style="min-width: {{ count($mainRounds) * $roundWidth }}px;
                    min-height: {{ $bracketHeight + $headerOffset + 200 }}px;">

                {{-- SVG --}}
                <svg class="absolute top-0 left-0 w-full h-full pointer-events-none"> @foreach($svgLines as $line)
                    <line
                        x1="{{ $line['x1'] }}"
                        y1="{{ $line['y1'] }}"
                        x2="{{ $line['x2'] }}"
                        y2="{{ $line['y2'] }}"
                        stroke="{{ $line['color'] }}"
                        stroke-width="{{ $line['color'] === '#22c55e' ? 3 : 2 }}" />
                    @endforeach
                </svg>

                {{-- MATCHES --}}
                @foreach($mainRounds as $round => $roundGames)

                @php
                $roundGames = $roundGames->sortBy('position')->values();
                $currentBestOf = $roundGames->first()?->best_of;
                $roundIndex = $round - 1;
                $x = $roundIndex * $roundWidth;
                @endphp

                {{-- RUNDENÜBERSCHRIFT --}}
                <div class="absolute text-center text-gray-400 text-sm font-semibold"
                    style="left: {{ $x }}px;
                top: 0px;
                width: 240px;">
                    {{ roundName($round, $mainRounds) }}
                    <form method="POST"
                        action="{{ route('tournaments.updateRoundBestOf', [$tournament, $round]) }}">
                        @csrf
                        @method('PATCH')

                        <select name="best_of"
                            onchange="this.form.submit()"
                            class="mt-1 w-20 text-xs font-semibold
               bg-gray-800 text-emerald-400
               border border-gray-700
               rounded-md px-2 py-1
               focus:outline-none focus:ring-2 focus:ring-emerald-500
               transition">
                            @foreach([1,3,5,7,9] as $value)
                            <option value="{{ $value }}"
                                {{ $currentBestOf == $value ? 'selected' : '' }}>
                                Bo{{ $value }}
                            </option>
                            @endforeach
                        </select>
                    </form>
                </div>

                @php
                $roundGames = $roundGames->sortBy('position')->values();
                $roundIndex = $round - 1;
                @endphp

                @foreach($roundGames as $i => $game)

                @php
                $y = $positions[$round][$i];
                $x = $roundIndex * $roundWidth;
                @endphp

                <div class="absolute"
                    style="left: {{ $x }}px;
                            top: {{ $y - ($matchHeight / 2) }}px;
                            width: 240px;">

                    @include('tournaments.partials._ko_game', [
                    'game' => $game,
                    'tournament' => $tournament,
                    'maxRound' => $finalRound
                    ])

                </div>

                @endforeach
                @endforeach

                {{-- Platz 3 --}}
                @if($thirdPlaceGame && $finalY !== null)

                @php
                $thirdY = $finalY + $matchHeight + 80;
                $thirdX = ($finalRound - 1) * $roundWidth + 20;
                @endphp

                {{-- Überschrift Platz 3 --}}
                <div class="absolute text-center text-amber-400 text-sm font-semibold"
                    style="left: {{ $thirdX }}px;
                top: {{ $thirdY - $matchHeight + 30 }}px;
                width: 240px;">
                    Spiel um Platz 3
                    <form method="POST"
                        action="{{ route('tournaments.updateRoundBestOf', [$tournament, $finalRound]) }}">
                        @csrf
                        @method('PATCH')

                        <input type="hidden" name="is_third_place" value="1">

                        @php
                        $thirdBestOf = $thirdPlaceGame?->best_of;
                        $hasResults = $thirdPlaceGame && $thirdPlaceGame->winner_id;
                        @endphp

                        <select name="best_of"
                            onchange="this.form.submit()"
                            {{ $hasResults ? 'disabled' : '' }}
                            class="mt-1 w-20 text-xs font-semibold
                   bg-gray-800 text-amber-400
                   border border-gray-700
                   rounded-md px-2 py-1
                   focus:outline-none focus:ring-2 focus:ring-amber-500
                   transition">

                            @foreach([1,3,5,7,9] as $value)
                            <option value="{{ $value }}"
                                {{ $thirdBestOf == $value ? 'selected' : '' }}>
                                Bo{{ $value }}
                            </option>
                            @endforeach
                        </select>
                    </form>
                </div>

                <div class="absolute"
                    style="left: {{ $thirdX }}px;
                top: {{ $thirdY - ($matchHeight / 2) }}px;
                width: 240px;">

                    @include('tournaments.partials._ko_game', [
                    'game' => $thirdPlaceGame,
                    'tournament' => $tournament,
                    'maxRound' => $finalRound
                    ])

                </div>

                @endif

            </div>

        </div>

    </div>
    @endif