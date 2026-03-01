@php
$maxRound = $tournament->games
->whereNull('group_id')
->max('round');

$final = $tournament->games
->where('round', $maxRound)
->where('is_third_place', false)
->first();

$thirdPlaceGame = $tournament->games
->where('round', $maxRound)
->where('is_third_place', true)
->first();

$first = null;
$second = null;
$third = null;

if ($final && $final->winner_id) {
$first = $final->winner;
$second = $final->winner_id == $final->player1_id
? $final->player2
: $final->player1;
}

if ($thirdPlaceGame && $thirdPlaceGame->winner_id) {
$third = $thirdPlaceGame->winner;
}
@endphp

@if($tournament->status === 'finished' && $first)

<div class="bg-gray-900 border border-gray-800 rounded-xl p-8 shadow-xl mt-8">

    <h2 class="text-center text-xl font-semibold text-white mb-8">
        🏆 Turnierergebnis
    </h2>

    <div class="flex justify-center items-end gap-6">

        {{-- 🥈 Platz 2 --}}
        @if($second)
        <div class="flex flex-col items-center">
            <div class="bg-gray-700 text-gray-200 px-6 py-3 rounded-t-lg font-semibold shadow">
                🥈 {{ $second->name }}
            </div>
            <div class="bg-gray-600 w-24 h-20 rounded-b-lg"></div>
        </div>
        @endif

        {{-- 🥇 Platz 1 --}}
        <div class="flex flex-col items-center">
            <div class="bg-yellow-500 text-gray-900 px-8 py-4 rounded-t-lg font-bold text-lg shadow-lg scale-110">
                🏆 {{ $first->name }}
            </div>
            <div class="bg-yellow-600 w-28 h-28 rounded-b-lg"></div>
        </div>

        {{-- 🥉 Platz 3 --}}
        @if($tournament->has_third_place && $third)
        <div class="flex flex-col items-center">
            <div class="bg-amber-600 text-white px-6 py-3 rounded-t-lg font-semibold shadow">
                🥉 {{ $third->name }}
            </div>
            <div class="bg-amber-700 w-24 h-16 rounded-b-lg"></div>
        </div>
        @endif

    </div>

</div>

@endif