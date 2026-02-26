<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-200">
            {{ $tournament->name }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto space-y-8">

            {{-- ===================== --}}
            {{-- TURNIERINFOS --}}
            {{-- ===================== --}}
            <div class="bg-gray-900 p-6 rounded-xl">
                <h3 class="text-lg text-gray-300 mb-2">Turnierinformationen</h3>

                <p class="text-gray-400">
                    Modus:
                    {{ $tournament->mode === 'ko' ? 'KO-System' : 'Gruppenphase + KO' }}
                </p>

                <p class="text-gray-400">
                    Status:
                    {{ ucfirst($tournament->status) }}
                </p>

                <p class="text-gray-400">
                    Teilnehmer: {{ $tournament->players->count() }}
                </p>

                @if($tournament->mode === 'group_ko')
                <p class="text-gray-400">
                    Gruppen: {{ $tournament->group_count }},
                    Weiter pro Gruppe: {{ $tournament->group_advance_count }}
                </p>
                @endif
            </div>

            {{-- ===================== --}}
            {{-- TURNIERSIEGER --}}
            {{-- ===================== --}}
            @php
            $koGames = $tournament->games->where('is_group_match', 0);

            $koPlayers = $koGames
            ->where('round', 1)
            ->flatMap(fn($g) => [$g->player1_id, $g->player2_id])
            ->unique()
            ->count();

            $totalRounds = $koPlayers > 0 ? log($koPlayers, 2) : null;

            $finalGame = null;

            if ($totalRounds) {
            $finalGame = $koGames
            ->where('round', $totalRounds)
            ->first();
            }
            @endphp

            @if ($tournament->status === 'finished' && $finalGame && $finalGame->winner_id)
            <div class="bg-emerald-700 p-6 rounded-xl text-center">
                <h2 class="text-2xl font-bold text-white">🏆 Turniersieger</h2>
                <p class="text-3xl mt-3 font-extrabold text-white">
                    {{ $finalGame->winner->name }}
                </p>
            </div>
            @endif

            {{-- ===================== --}}
            {{-- GRUPPENPHASE --}}
            {{-- ===================== --}}
            @if ($tournament->status === 'group_running')

            @foreach ($tournament->groups as $group)

            @php
            $table = app(\App\Services\GroupTableCalculator::class)
            ->calculate($group);
            @endphp

            <div class="bg-gray-900 rounded-xl p-6">
                <h3 class="text-lg text-gray-300 mb-4">
                    Gruppe {{ $group->name }}
                </h3>

                <table class="w-full text-sm text-gray-300 mb-6">
                    <thead class="border-b border-gray-700 text-gray-400">
                        <tr>
                            <th>#</th>
                            <th>Spieler</th>
                            <th>Punkte</th>
                            <th>Differenz</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($table as $index => $row)
                        <tr class="border-b border-gray-800 {{ $index < $tournament->group_advance_count ? 'bg-emerald-900/30' : '' }}">
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $row['player']->name }}</td>
                            <td>{{ $row['points'] }}</td>
                            <td class="{{ $row['difference'] >= 0 ? 'text-emerald-400' : 'text-red-400' }}">
                                {{ $row['difference'] }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>

                {{-- Gruppenspiele --}}
                <div class="space-y-3">
                    @foreach ($group->games as $game)
                    <div class="bg-gray-800 rounded p-4 flex justify-between items-center">

                        <div>
                            {{ $game->player1->name }}
                            :
                            {{ $game->player2->name }}
                        </div>

                        @if ($game->winner_id)
                        <div class="text-sm text-gray-400">
                            {{ $game->player1_score }} :
                            {{ $game->player2_score }}
                            (Bo{{ $game->best_of }})
                        </div>
                        @else
                        <form method="POST"
                            action="{{ route('games.updateScore', $game) }}"
                            class="flex space-x-1">
                            @csrf
                            <input type="number" name="player1_score"
                                class="w-12 bg-gray-700 text-white text-center rounded">
                            :
                            <input type="number" name="player2_score"
                                class="w-12 bg-gray-700 text-white text-center rounded">
                            <button class="px-2 bg-emerald-600 rounded text-xs">✓</button>
                        </form>
                        @endif

                    </div>
                    @endforeach
                </div>

            </div>

            @endforeach

            @php
            $allFinished = $tournament->groups
            ->flatMap(fn($g) => $g->games)
            ->whereNull('winner_id')
            ->count() === 0;
            @endphp

            @if($allFinished)
            <form method="POST"
                action="{{ route('tournaments.startKo', $tournament) }}">
                @csrf
                <button class="px-6 py-2 bg-indigo-600 rounded-lg">
                    🏁 KO-Phase starten
                </button>
            </form>
            @endif

            @endif

            {{-- ===================== --}}
            {{-- KO PHASE --}}
            {{-- ===================== --}}
            @if ($tournament->status === 'ko_running' || $tournament->status === 'finished')

            @php
            $groupedGames = $koGames
            ->sortBy('position')
            ->groupBy('round');
            @endphp

            <div class="flex space-x-12 overflow-x-auto">

                @foreach ($groupedGames as $round => $games)

                @if($round == $totalRounds + 1)
                @continue
                @endif

                @php
                $roundsFromEnd = $totalRounds - $round + 1;

                switch ($roundsFromEnd) {
                case 1: $roundName = '🏆 Finale'; break;
                case 2: $roundName = 'Halbfinale'; break;
                case 3: $roundName = 'Viertelfinale'; break;
                case 4: $roundName = 'Achtelfinale'; break;
                default: $roundName = 'Runde ' . $round;
                }
                @endphp

                <div class="flex flex-col space-y-6">
                    <h3 class="text-center text-gray-300 font-semibold">
                        {{ $roundName }}
                    </h3>

                    @foreach ($games as $game)
                    <div class="bg-gray-900 p-4 rounded shadow text-center w-64">

                        <div>
                            {{ $game->player1->name ?? '—' }}
                            :
                            {{ $game->player2->name ?? '—' }}
                        </div>

                        @if ($game->winner_id)
                        <div class="text-sm text-gray-400 mt-2">
                            {{ $game->player1_score }} :
                            {{ $game->player2_score }}
                            (Bo{{ $game->best_of }})
                        </div>
                        @else
                        <form method="POST"
                            action="{{ route('games.updateScore', $game) }}"
                            class="mt-2 flex justify-center space-x-1">
                            @csrf
                            <input type="number" name="player1_score"
                                class="w-12 bg-gray-800 text-white text-center rounded">
                            :
                            <input type="number" name="player2_score"
                                class="w-12 bg-gray-800 text-white text-center rounded">
                            <button class="px-2 bg-emerald-600 rounded text-xs">✓</button>
                        </form>
                        @endif

                    </div>
                    @endforeach
                </div>

                @endforeach

            </div>

            @endif

        </div>
    </div>
</x-app-layout>