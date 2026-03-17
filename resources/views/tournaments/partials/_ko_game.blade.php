{{-- 
|--------------------------------------------------------------------------
| 🎯 KO GAME COMPONENT (AJAX READY)
|--------------------------------------------------------------------------
|
| Diese Komponente stellt ein einzelnes KO-Spiel dar.
|
| Unterstützt:
| - Ergebnis eingeben (AJAX)
| - Ergebnis löschen (AJAX)
| - Dynamisches Neuladen via JS (reloadGame)
|
| Wichtig:
| - Jeder Game-Block hat data-game (für DOM-Update)
| - Kein klassischer Reload mehr notwendig
|
--}}

<div class="game-wrapper" data-game="{{ $game->id }}" data-finished="{{ $game->winner_id ? 1 : 0 }}">

    <div
        class="bg-gray-800 border border-gray-700 rounded-lg p-4 shadow mb-4 min-w-[240px] min-h-[160px] flex flex-col justify-center relative">

        {{-- 🔹 FALL 1: Spiel ist noch offen UND KO läuft --}}
        @if (!$game->winner_id && $tournament->status === 'ko_running')

            {{-- 
            |--------------------------------------------------------------------------
            | 📝 SCORE FORM (AJAX)
            |--------------------------------------------------------------------------
            |
            | Wird per JS abgefangen (kein Reload!)
            |
            --}}
            <form method="POST" action="{{ route('games.updateScore', $game) }}"
                class="space-y-3 simulate-ko-form score-form" data-game="{{ $game->id }}"
                data-round="{{ $game->round }}" data-bestof="{{ $game->best_of }}">

                @csrf

                {{-- 🔹 PLAYER 1 --}}
                <div class="flex justify-between items-center text-sm">
                    <span>
                        {{ $game->player1->name ?? $game->player1_source }}
                    </span>

                    <input type="number" name="player1_score" min="0" required
                        class="w-12 bg-gray-900 border border-gray-700 rounded text-center text-white">
                </div>

                {{-- 🔹 PLAYER 2 --}}
                <div class="flex justify-between items-center text-sm">
                    <span>
                        {{ $game->player2->name ?? $game->player2_source }}
                    </span>

                    <input type="number" name="player2_score" min="0" required
                        class="w-12 bg-gray-900 border border-gray-700 rounded text-center text-white">
                </div>

                {{-- 🔹 SUBMIT --}}
                <button type="submit" class="absolute top-2 right-3 text-green-400 hover:text-green-300 text-sm">
                    ✅
                </button>

            </form>

            {{-- 🔹 FALL 2: Spiel ist abgeschlossen --}}
        @else
            {{-- 
            |--------------------------------------------------------------------------
            | 🗑 RESET BUTTON (AJAX)
            |--------------------------------------------------------------------------
            |
            | Wird ebenfalls per JS abgefangen
            |
            --}}
            <div class="flex justify-end mb-2">
                <form method="POST" action="{{ route('games.updateScore', $game) }}"
                    class="space-y-3 simulate-ko-form score-form" data-game="{{ $game->id }}"
                    data-round="{{ $game->round }}" data-bestof="{{ $game->best_of }}">

                    @csrf

                    <button type="submit" class="text-red-500 hover:text-red-400 text-xs">
                        🗑
                    </button>
                </form>
            </div>

            {{-- 
            |--------------------------------------------------------------------------
            | 🧠 STATUS LOGIK
            |--------------------------------------------------------------------------
            --}}
            @php
                $isFinal = !$game->is_third_place && $game->round === $maxRound;
                $isThird = $game->is_third_place;

                $p1Winner = (int) $game->winner_id === (int) $game->player1_id;
                $p2Winner = (int) $game->winner_id === (int) $game->player2_id;
            @endphp

            {{-- 
            |--------------------------------------------------------------------------
            | 🎨 SPIELDARSTELLUNG
            |--------------------------------------------------------------------------
            --}}
            <div class="space-y-2 text-sm">

                {{-- 🔹 PLAYER 1 --}}
                <div
                    class="flex justify-between items-center px-3 py-2 rounded

                    @if ($isFinal && $p1Winner) bg-yellow-500/20 border border-yellow-400/50
                    @elseif($isFinal && !$p1Winner && $game->winner_id)
                        bg-gray-400/20 border border-gray-300/40
                    @elseif($isThird && $p1Winner)
                        bg-amber-600/20 border border-amber-500/50
                    @elseif($p1Winner)
                        bg-green-600/20 border border-green-500/40
                    @else
                        bg-gray-900 border border-gray-700 opacity-70 @endif">

                    <span
                        class="
                        @if ($isFinal && $p1Winner) text-yellow-400 font-bold
                        @elseif($isFinal && !$p1Winner && $game->winner_id) text-gray-300 font-semibold
                        @elseif($isThird && $p1Winner) text-amber-400 font-semibold
                        @elseif($p1Winner) text-green-400 font-semibold
                        @else text-gray-400 @endif">

                        {{-- 🏆 Icons --}}
                        @if ($isFinal && $p1Winner)
                            🏆
                        @endif
                        @if ($isFinal && !$p1Winner && $game->winner_id)
                            🥈
                        @endif
                        @if ($isThird && $p1Winner)
                            🥉
                        @endif

                        {{ $game->player1->name ?? $game->player1_source }}
                    </span>

                    <span class="font-mono">
                        {{ $game->player1_score ?? '-' }}
                    </span>
                </div>

                {{-- 🔹 PLAYER 2 --}}
                <div
                    class="flex justify-between items-center px-3 py-2 rounded

                    @if ($isFinal && $p2Winner) bg-yellow-500/20 border border-yellow-400/50
                    @elseif($isFinal && !$p2Winner && $game->winner_id)
                        bg-gray-400/20 border border-gray-300/40
                    @elseif($isThird && $p2Winner)
                        bg-amber-600/20 border border-amber-500/50
                    @elseif($p2Winner)
                        bg-green-600/20 border border-green-500/40
                    @else
                        bg-gray-900 border border-gray-700 opacity-70 @endif">

                    <span
                        class="
                        @if ($isFinal && $p2Winner) text-yellow-400 font-bold
                        @elseif($isFinal && !$p2Winner && $game->winner_id) text-gray-300 font-semibold
                        @elseif($isThird && $p2Winner) text-amber-400 font-semibold
                        @elseif($p2Winner) text-green-400 font-semibold
                        @else text-gray-400 @endif">

                        @if ($isFinal && $p2Winner)
                            🏆
                        @endif
                        @if ($isFinal && !$p2Winner && $game->winner_id)
                            🥈
                        @endif
                        @if ($isThird && $p2Winner)
                            🥉
                        @endif

                        {{ $game->player2->name ?? $game->player2_source }}
                    </span>

                    <span class="font-mono">
                        {{ $game->player2_score ?? '-' }}
                    </span>
                </div>

            </div>

        @endif

    </div>
</div>
