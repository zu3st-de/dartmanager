{{-- 
|--------------------------------------------------------------------------
| 🟢 GROUPS (AJAX VERSION – FINAL)
|--------------------------------------------------------------------------
|
| Features:
| - AJAX Score Submit
| - AJAX Reset
| - Live Tabellen Update
| - AutoSim kompatibel
|
--}}

@if ($tournament->status === 'group_running')

    <div class="bg-gray-900 border border-gray-800 rounded-xl p-6 shadow-lg">

        {{-- HEADER --}}
        <div class="flex items-center justify-between mb-4">

            <h2 class="text-xl font-bold">Gruppenphase</h2>

            {{-- 🔥 AJAX Best-of --}}
            <select onchange="updateGroupBestOf(this, {{ $tournament->id }})"
                class="bg-gray-800 text-xs rounded border border-gray-700 
                   text-emerald-400 px-2 py-1">

                @foreach ([1, 3, 5, 7] as $value)
                    <option value="{{ $value }}" {{ $groupBestOf == $value ? 'selected' : '' }}>
                        Bo{{ $value }}
                    </option>
                @endforeach
            </select>

        </div>

        {{-- 🔹 GROUPS --}}
        <div class="flex gap-8 flex-wrap">

            @foreach ($tournament->groups as $group)
                <div class="min-w-[320px]" data-group="{{ $group->id }}">

                    <h3 class="text-sm text-gray-400 mb-4">
                        Gruppe {{ $group->name }}
                    </h3>

                    {{-- 🔥 TABLE WRAPPER (WICHTIG!) --}}
                    <div class="group-table mb-6" data-group-table="{{ $group->id }}">

                        @include('tournaments.partials._group_table', [
                            'group' => $group,
                            'tournament' => $tournament,
                        ])

                    </div>

                    {{-- 🔹 GAMES --}}
                    @foreach ($group->games as $game)
                        <div class="bg-gray-800 border border-gray-700 rounded-lg p-4 shadow mb-4"
                            data-game="{{ $game->id }}">

                            {{-- 🟢 OFFEN --}}
                            @if (!$game->winner_id)
                                <form method="POST" action="{{ route('games.updateScore', $game) }}"
                                    class="simulate-group-form group-score-form" data-game="{{ $game->id }}"
                                    data-group="{{ $group->id }}" data-bestof="{{ $game->best_of }}">

                                    @csrf

                                    <div class="flex justify-end mb-2">
                                        <button type="submit" class="text-green-400 text-sm">
                                            ✅
                                        </button>
                                    </div>

                                    <div class="flex justify-between">
                                        <span>{{ $game->player1->name ?? 'TBD' }}</span>
                                        <input type="number" name="player1_score" required
                                            class="w-12 bg-gray-900 border text-center">
                                    </div>

                                    <div class="flex justify-between">
                                        <span>{{ $game->player2->name ?? 'TBD' }}</span>
                                        <input type="number" name="player2_score" required
                                            class="w-12 bg-gray-900 border text-center">
                                    </div>

                                </form>

                                {{-- 🔴 FERTIG --}}
                            @else
                                <div class="flex justify-end mb-2">
                                    <form method="POST" action="{{ route('games.reset', $game) }}"
                                        class="group-reset-form" data-game="{{ $game->id }}"
                                        data-group="{{ $group->id }}">
                                        @csrf
                                        <button class="text-red-400 text-xs">🗑</button>
                                    </form>
                                </div>

                                {{-- Anzeige --}}
                                <div class="text-sm">
                                    {{ $game->player1->name }} ({{ $game->player1_score }})<br>
                                    {{ $game->player2->name }} ({{ $game->player2_score }})
                                </div>
                            @endif

                        </div>
                    @endforeach

                </div>
            @endforeach

        </div>

    </div>
@endif
