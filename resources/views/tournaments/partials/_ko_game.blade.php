<div class="bg-gray-800 border border-gray-700 rounded-lg p-4 shadow mb-4 min-w-[240px] min-h-[160px] flex flex-col justify-center">

    {{-- OFFEN --}}
    @if(!$game->winner_id && $tournament->status === 'ko_running')

    <form method="POST"
        action="{{ route('games.updateScore', $game) }}"
        class="space-y-3">

        @csrf

        <div class="flex justify-between items-center text-sm">
            <span>{{ $game->player1->name ?? 'TBD' }}</span>
            <input type="number"
                name="player1_score"
                min="0"
                required
                class="w-12 bg-gray-900 border border-gray-700 rounded text-center text-white">
        </div>

        <div class="flex justify-between items-center text-sm">
            <span>{{ $game->player2->name ?? 'TBD' }}</span>
            <input type="number"
                name="player2_score"
                min="0"
                required
                class="w-12 bg-gray-900 border border-gray-700 rounded text-center text-white">
        </div>

        <button class="hidden w-full bg-blue-600 hover:bg-blue-500 transition text-white text-xs py-2 rounded">
            Ergebnis speichern
        </button>

    </form>

    @else

    @php
    $isFinal = !$game->is_third_place && $game->round === $maxRound;
    $isThird = $game->is_third_place;

    $p1Winner = (int) $game->winner_id === (int) $game->player1_id;
    $p2Winner = (int) $game->winner_id === (int) $game->player2_id;
    @endphp

    <div class="space-y-2 text-sm">

        {{-- PLAYER 1 --}}
        <div class="flex justify-between items-center px-3 py-2 rounded
                @if($isFinal && $p1Winner)
                    bg-yellow-500/20 border border-yellow-400/50
                @elseif($isFinal && !$p1Winner && $game->winner_id)
                    bg-gray-400/20 border border-gray-300/40
                @elseif($isThird && $p1Winner)
                    bg-amber-600/20 border border-amber-500/50
                @elseif($p1Winner)
                    bg-green-600/20 border border-green-500/40
                @else
                    bg-gray-900 border border-gray-700 opacity-70
                @endif">

            <span class="
                    @if($isFinal && $p1Winner)
                        text-yellow-400 font-bold
                    @elseif($isFinal && !$p1Winner && $game->winner_id)
                        text-gray-300 font-semibold
                    @elseif($isThird && $p1Winner)
                        text-amber-400 font-semibold
                    @elseif($p1Winner)
                        text-green-400 font-semibold
                    @else
                        text-gray-400
                    @endif">

                @if($isFinal && $p1Winner) 🏆 @endif
                @if($isFinal && !$p1Winner && $game->winner_id) 🥈 @endif
                @if($isThird && $p1Winner) 🥉 @endif

                {{ $game->player1->name ?? 'TBD' }}
            </span>

            <span class="font-mono">
                {{ $game->player1_score ?? '-' }}
            </span>
        </div>

        {{-- PLAYER 2 --}}
        <div class="flex justify-between items-center px-3 py-2 rounded
                @if($isFinal && $p2Winner)
                    bg-yellow-500/20 border border-yellow-400/50
                @elseif($isFinal && !$p2Winner && $game->winner_id)
                    bg-gray-400/20 border border-gray-300/40
                @elseif($isThird && $p2Winner)
                    bg-amber-600/20 border border-amber-500/50
                @elseif($p2Winner)
                    bg-green-600/20 border border-green-500/40
                @else
                    bg-gray-900 border border-gray-700 opacity-70
                @endif">

            <span class="
                    @if($isFinal && $p2Winner)
                        text-yellow-400 font-bold
                    @elseif($isFinal && !$p2Winner && $game->winner_id)
                        text-gray-300 font-semibold
                    @elseif($isThird && $p2Winner)
                        text-amber-400 font-semibold
                    @elseif($p2Winner)
                        text-green-400 font-semibold
                    @else
                        text-gray-400
                    @endif">

                @if($isFinal && $p2Winner) 🏆 @endif
                @if($isFinal && !$p2Winner && $game->winner_id) 🥈 @endif
                @if($isThird && $p2Winner) 🥉 @endif

                {{ $game->player2->name ?? 'TBD' }}
            </span>

            <span class="font-mono">
                {{ $game->player2_score ?? '-' }}
            </span>
        </div>

    </div>

    @endif

</div>