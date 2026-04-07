@php
    $participantsReady = $game->player1_id && $game->player2_id;
@endphp

<div
    class="bg-gray-800 border border-gray-700 rounded-lg p-4 shadow mb-4 min-w-[240px] min-h-[160px] flex flex-col justify-center relative">


    @if (!$game->winner_id && $tournament->status === 'ko_running' && $participantsReady)
        <form method="POST" class="simulate-ko-form score-form" data-url="{{ route('games.score', $game) }}"
            data-game-id="{{ $game->id }}" data-round="{{ $game->round }}">

            @csrf

            <div class="flex justify-between items-center text-sm mb-2">
                <span>{{ $game->player1->name ?? $game->player1_source }}</span>

                <input type="number" name="player1_score"
                    class="score-input w-12 bg-gray-900 border border-gray-700 rounded text-center text-white">
            </div>

            <div class="flex justify-between items-center text-sm mb-2">
                <span>{{ $game->player2->name ?? $game->player2_source }}</span>

                <input type="number" name="player2_score"
                    class="score-input w-12 bg-gray-900 border border-gray-700 rounded text-center text-white">
            </div>

            <button type="button" class="save-btn absolute top-2 right-3 text-green-400 hover:text-green-300 text-sm">
                ✅
            </button>

        </form>
    @else
        @php
            $canReset = $game->canBeReset();
        @endphp

        @if ($canReset)
            <div class="flex justify-end mb-2">
                <form method="POST" class="reset-form" data-url="{{ route('games.reset', $game) }}"
                    data-game-id="{{ $game->id }}">

                    @csrf

                    <button type="button" class="text-red-500 hover:text-red-400 text-xs">
                        🗑
                    </button>
                </form>
            </div>
        @endif

        @php
            $p1Winner = (int) $game->winner_id === (int) $game->player1_id;
            $p2Winner = (int) $game->winner_id === (int) $game->player2_id;
            $showWinnerState = $participantsReady && $game->winner_id;
            $showWaitingHint = ! $participantsReady && ! ($game->player1_id xor $game->player2_id);
        @endphp

        <div class="space-y-2 text-sm">
            @if ($showWaitingHint)
                <div class="text-[11px] uppercase tracking-wide text-gray-500 mb-1">
                    Wartet auf Teilnehmer
                </div>
            @endif

            <div
                class="flex justify-between items-center px-3 py-2 rounded
            @if ($showWinnerState && $p1Winner) bg-green-600/20 border border-green-500/40
            @else bg-gray-900 border border-gray-700 opacity-70 @endif">

                <span>{{ $game->player1->name ?? $game->player1_source }}</span>
                <span class="font-mono">{{ $game->player1_score ?? '-' }}</span>
            </div>

            <div
                class="flex justify-between items-center px-3 py-2 rounded
            @if ($showWinnerState && $p2Winner) bg-green-600/20 border border-green-500/40
            @else bg-gray-900 border border-gray-700 opacity-70 @endif">

                <span>{{ $game->player2->name ?? $game->player2_source }}</span>
                <span class="font-mono">{{ $game->player2_score ?? '-' }}</span>
            </div>

        </div>
    @endif


</div>
