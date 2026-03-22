@php
    $bestOf = (int) ($game->best_of ?? 1);
@endphp

<div class="game-wrapper mb-4" data-game="{{ $game->id }}" data-group="{{ $game->group_id }}"
    data-finished="{{ $game->winner_id ? '1' : '0' }}">

    <div class="bg-gray-800 border border-gray-700 rounded-lg p-4 shadow min-w-[240px] relative">

        {{-- ============================================================
            📝 SPIEL NOCH OFFEN
        ============================================================ --}}
        @if (!$game->winner_id && $tournament->status === 'group_running')

            {{-- ✅ BUTTON (jetzt auf Card-Ebene, nicht im Form!) --}}
            <button type="submit" form="game-form-{{ $game->id }}"
                class="save-btn absolute top-2 right-2 text-green-400 hover:text-green-300 text-sm z-10">
                ✅
            </button>

            <form id="game-form-{{ $game->id }}" method="POST" action="{{ route('games.updateScore', $game) }}"
                data-url="{{ route('games.updateScore', $game) }}" data-bestof="{{ $game->best_of }}"
                data-group="{{ $game->group_id }}" data-game="{{ $game->id }}"
                class="score-form group-score-form simulate-group-form">

                @csrf

                {{-- 📦 CONTENT --}}
                <div class="space-y-3 mt-4 text-sm">

                    {{-- 🔹 PLAYER 1 --}}
                    <div class="flex justify-between items-center px-3 h-10 rounded border border-gray-700 bg-gray-900">
                        <span>
                            {{ $game->player1->name ?? '-' }}
                        </span>

                        <input type="number" name="player1_score" min="0"
                            class="group-score-input w-12 h-8 bg-transparent border-0 text-center rounded text-white focus:outline-none focus:ring-1 focus:ring-gray-500">
                    </div>

                    {{-- 🔹 PLAYER 2 --}}
                    <div class="flex justify-between items-center px-3 h-10 rounded border border-gray-700 bg-gray-900">
                        <span>
                            {{ $game->player2->name ?? '-' }}
                        </span>

                        <input type="number" name="player2_score" min="0"
                            class="group-score-input w-12 h-8 bg-transparent border-0 text-center rounded text-white focus:outline-none focus:ring-1 focus:ring-gray-500">
                    </div>

                    {{-- 🎯 REST (nur BO1) --}}
                    @if ($bestOf === 1)
                        <div class="flex justify-between items-center text-xs text-gray-400">
                            <span>Rest</span>

                            <input type="number" name="winning_rest" min="0" max="501"
                                class="w-16 h-8 bg-transparent border border-gray-600 rounded text-center text-white focus:outline-none focus:border-green-500">
                        </div>
                    @endif

                </div>

            </form>


            {{-- ============================================================
            🧾 SPIEL ABGESCHLOSSEN
        ============================================================ --}}
        @else
            {{-- 🗑 RESET BUTTON (gleich positioniert wie ✅) --}}
            <button type="submit" form="reset-form-{{ $game->id }}"
                class="absolute top-2 right-2 text-red-500 hover:text-red-400 text-sm">
                🗑
            </button>

            <form id="reset-form-{{ $game->id }}" method="POST" action="{{ route('games.reset', $game) }}"
                class="hidden">
                @csrf
            </form>

            @php
                $p1Winner = (int) $game->winner_id === (int) $game->player1_id;
                $p2Winner = (int) $game->winner_id === (int) $game->player2_id;
            @endphp

            <div class="space-y-3 mt-4 text-sm">

                {{-- 🔹 PLAYER 1 --}}
                <div
                    class="flex justify-between items-center px-3 h-10 rounded border
                    {{ $p1Winner ? 'bg-green-600/20 border-green-500/40' : 'bg-gray-900 border-gray-700 opacity-70' }}">

                    <span class="{{ $p1Winner ? 'text-green-400 font-semibold' : 'text-gray-400' }}">
                        {{ $game->player1->name ?? '-' }}
                    </span>

                    <span class="font-mono">
                        {{ $game->player1_score ?? '-' }}
                    </span>
                </div>

                {{-- 🔹 PLAYER 2 --}}
                <div
                    class="flex justify-between items-center px-3 h-10 rounded border
                    {{ $p2Winner ? 'bg-green-600/20 border-green-500/40' : 'bg-gray-900 border-gray-700 opacity-70' }}">

                    <span class="{{ $p2Winner ? 'text-green-400 font-semibold' : 'text-gray-400' }}">
                        {{ $game->player2->name ?? '-' }}
                    </span>

                    <span class="font-mono">
                        {{ $game->player2_score ?? '-' }}
                    </span>
                </div>

                {{-- 🎯 REST --}}
                @if ($bestOf === 1 && $game->winning_rest !== null)
                    <div class="text-xs text-gray-400 text-center">
                        Rest: {{ $game->winning_rest }}
                    </div>
                @endif

            </div>

        @endif

    </div>
</div>
