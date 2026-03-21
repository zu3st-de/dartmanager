@php
    $bestOf = (int) ($game->best_of ?? 1);
@endphp

<div class="game-wrapper" data-game="{{ $game->id }}">

    <div class="bg-gray-800 border border-gray-700 rounded-lg p-4 shadow min-w-[240px] relative">
        @php
            $bestOf = (int) ($game->best_of ?? 1);
        @endphp
        {{-- 
        |--------------------------------------------------------------------------
        | 🎮 EINZELNES SPIEL
        |--------------------------------------------------------------------------
        |
        | Ähnlich wie dein KO-Game, aber für Gruppenphase
        |
        --}}
        <div class="game-wrapper" data-game="{{ $game->id }}" data-finished="{{ $game->winner_id ? '1' : '0' }}">

            <div class="bg-gray-800 border border-gray-700 rounded-lg p-4 shadow min-w-[240px] relative">

                {{-- 
                |--------------------------------------------------------------------------
                | 📝 FALL 1: SPIEL NOCH OFFEN
                |--------------------------------------------------------------------------
                --}}
                @if (!$game->winner_id && $tournament->status === 'group_running')
                    <form method="POST" action="{{ route('games.updateScore', $game) }}"
                        data-url="{{ route('games.updateScore', $game) }}" data-bestof="{{ $game->best_of }}"
                        class="score-form group-score-form simulate-group-form space-y-3"
                        data-group="{{ $game->group_id }}" data-game="{{ $game->id }}">

                        @csrf

                        {{-- 🔹 PLAYER 1 --}}
                        <div class="flex justify-between items-center text-sm">
                            <span>
                                {{ $game->player1->name ?? '-' }}
                            </span>

                            <input type="number" name="player1_score" min="0"
                                class="w-12 bg-gray-900 border border-gray-700 rounded text-center text-white group-score-input">
                        </div>

                        {{-- 🔹 PLAYER 2 --}}
                        <div class="flex justify-between items-center text-sm">
                            <span>
                                {{ $game->player2->name ?? '-' }}
                            </span>

                            <input type="number" name="player2_score" min="0"
                                class="w-12 bg-gray-900 border border-gray-700 rounded text-center text-white group-score-input">
                        </div>

                        {{-- 
                        |--------------------------------------------------------------------------
                        | 🎯 REST FELD (nur bei BO1!)
                        |--------------------------------------------------------------------------
                        --}}
                        @if ($bestOf === 1)
                            <div class="text-xs text-gray-400">
                                Rest
                            </div>

                            <input type="number" name="winning_rest" min="0" max="501"
                                class="w-full bg-gray-900 border border-gray-700 rounded text-center text-white">
                        @endif

                        {{-- SUBMIT --}}
                        <button type="submit"
                            class="save-btn absolute top-2 right-3 text-green-400 hover:text-green-300 text-sm">
                            ✅
                        </button>

                    </form>

                    {{-- 
                |--------------------------------------------------------------------------
                | 🧾 FALL 2: SPIEL ABGESCHLOSSEN
                |--------------------------------------------------------------------------
                --}}
                @else
                    {{-- RESET BUTTON --}}
                    <div class="flex justify-end mb-2">
                        <form method="POST" action="{{ route('games.reset', $game) }}" class="reset-form"
                            data-game="{{ $game->id }}">

                            @csrf

                            <button type="submit" class="text-red-500 hover:text-red-400 text-xs">
                                🗑
                            </button>
                        </form>
                    </div>

                    @php
                        $p1Winner = (int) $game->winner_id === (int) $game->player1_id;
                        $p2Winner = (int) $game->winner_id === (int) $game->player2_id;
                    @endphp

                    <div class="space-y-2 text-sm">

                        {{-- PLAYER 1 --}}
                        <div
                            class="flex justify-between items-center px-3 py-2 rounded
                            {{ $p1Winner ? 'bg-green-600/20 border border-green-500/40' : 'bg-gray-900 border border-gray-700 opacity-70' }}">

                            <span class="{{ $p1Winner ? 'text-green-400 font-semibold' : 'text-gray-400' }}">
                                {{ $game->player1->name ?? '-' }}
                            </span>

                            <span class="font-mono">
                                {{ $game->player1_score ?? '-' }}
                            </span>
                        </div>

                        {{-- PLAYER 2 --}}
                        <div
                            class="flex justify-between items-center px-3 py-2 rounded
                            {{ $p2Winner ? 'bg-green-600/20 border border-green-500/40' : 'bg-gray-900 border border-gray-700 opacity-70' }}">

                            <span class="{{ $p2Winner ? 'text-green-400 font-semibold' : 'text-gray-400' }}">
                                {{ $game->player2->name ?? '-' }}
                            </span>

                            <span class="font-mono">
                                {{ $game->player2_score ?? '-' }}
                            </span>
                        </div>

                        {{-- REST anzeigen (nur BO1) --}}
                        @if ($bestOf === 1 && $game->winning_rest !== null)
                            <div class="text-xs text-gray-400 text-center">
                                Rest: {{ $game->winning_rest }}
                            </div>
                        @endif

                    </div>
                @endif

            </div>
        </div>
    </div>
</div>
