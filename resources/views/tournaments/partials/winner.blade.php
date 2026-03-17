@php
    /*
|--------------------------------------------------------------------------
| 🧮 GRUNDLAGEN BERECHNEN
|--------------------------------------------------------------------------
*/

    // Höchste Runde im KO (Finale)
    $maxRound = $tournament->games->whereNull('group_id')->max('round');

    // Finale (kein Spiel um Platz 3)
    $final = $tournament->games->where('round', $maxRound)->where('is_third_place', false)->first();

    // Spiel um Platz 3
    $thirdPlaceGame = $tournament->games->where('round', $maxRound)->where('is_third_place', true)->first();

    /*
|--------------------------------------------------------------------------
| 🏆 PLATZIERUNGEN INITIALISIEREN
|--------------------------------------------------------------------------
*/
    $first = null;
    $second = null;
    $third = null;

    /*
|--------------------------------------------------------------------------
| 🥇🥈 PLATZ 1 & 2 AUS FINALE BESTIMMEN
|--------------------------------------------------------------------------
*/
    if ($final && $final->winner_id) {
        // Gewinner des Finales = Platz 1
        $first = $final->winner;

        // Verlierer des Finales = Platz 2
        $second = $final->winner_id == $final->player1_id ? $final->player2 : $final->player1;
    }

    /*
|--------------------------------------------------------------------------
| 🥉 PLATZ 3 AUS EXTRA-SPIEL
|--------------------------------------------------------------------------
*/
    if ($thirdPlaceGame && $thirdPlaceGame->winner_id) {
        $third = $thirdPlaceGame->winner;
    }
@endphp

{{-- 🔹 Nur anzeigen wenn Turnier beendet und Sieger vorhanden --}}
@if ($tournament->status === 'finished' && $first)

    <div class="bg-gray-900 border border-gray-800 rounded-xl p-8 shadow-xl mt-8">

        {{-- Titel --}}
        <h2 class="text-center text-xl font-semibold text-white mb-8">
            🏆 Turnierergebnis
        </h2>

        {{-- Podium-Layout --}}
        <div class="flex justify-center items-end gap-6">

            {{-- 🥈 Platz 2 --}}
            @if ($second)
                <div class="flex flex-col items-center">

                    {{-- Name --}}
                    <div class="bg-gray-700 text-gray-200 px-6 py-3 rounded-t-lg font-semibold shadow">
                        🥈 {{ $second->name }}
                    </div>

                    {{-- Podest --}}
                    <div class="bg-gray-600 w-24 h-20 rounded-b-lg"></div>
                </div>
            @endif

            {{-- 🥇 Platz 1 (größer hervorgehoben) --}}
            <div class="flex flex-col items-center">

                {{-- Name --}}
                <div class="bg-yellow-500 text-gray-900 px-8 py-4 rounded-t-lg font-bold text-lg shadow-lg scale-110">
                    🏆 {{ $first->name }}
                </div>

                {{-- Podest (höher als Platz 2 & 3) --}}
                <div class="bg-yellow-600 w-28 h-28 rounded-b-lg"></div>
            </div>

            {{-- 🥉 Platz 3 (nur wenn aktiviert) --}}
            @if ($tournament->has_third_place && $third)
                <div class="flex flex-col items-center">

                    {{-- Name --}}
                    <div class="bg-amber-600 text-white px-6 py-3 rounded-t-lg font-semibold shadow">
                        🥉 {{ $third->name }}
                    </div>

                    {{-- Podest --}}
                    <div class="bg-amber-700 w-24 h-16 rounded-b-lg"></div>
                </div>
            @endif

        </div>

    </div>

@endif
