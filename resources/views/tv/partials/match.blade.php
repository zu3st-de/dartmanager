@php
$p1Winner = $game->winner_id === $game->player1_id;
$p2Winner = $game->winner_id === $game->player2_id;
@endphp

<div class="bg-gray-900 border border-gray-800 rounded-lg p-3 w-60 relative">

    <div class="flex justify-between">

        <span class="{{ $p1Winner ? 'text-green-400 font-semibold' : '' }}">
            {{ $game->player1->name ?? 'TBD' }}
        </span>

        <span>
            {{ $game->player1_score ?? '-' }}
        </span>

    </div>

    <div class="flex justify-between">

        <span class="{{ $p2Winner ? 'text-green-400 font-semibold' : '' }}">
            {{ $game->player2->name ?? 'TBD' }}
        </span>

        <span>
            {{ $game->player2_score ?? '-' }}
        </span>

    </div>

</div>