<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-200">
            {{ $tournament->name }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto space-y-6">

            {{-- TURNIERINFOS --}}
            <div class="bg-gray-900 p-6 rounded-xl">
                <h3 class="text-lg text-gray-300 mb-2">
                    Turnierinformationen
                </h3>

                <p class="text-gray-400">
                    Modus:
                    {{ $tournament->mode === 'ko' ? 'KO-System' : 'Gruppenphase + KO' }}
                </p>

                <p class="text-gray-400">
                    Status:
                    <span class="font-semibold">
                        {{ ucfirst($tournament->status) }}
                    </span>
                </p>

                <p class="text-gray-400">
                    Teilnehmer:
                    (<span id="player-count">{{ $tournament->players->count() }}</span>)
                </p>

                {{-- Auslosen --}}
                @if ($tournament->status === 'draft')
                <form method="POST" action="{{ route('tournaments.draw', $tournament) }}" class="mt-3">
                    @csrf
                    <button class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 rounded-lg">
                        🔀 Auslosen
                    </button>
                </form>
                @endif

                {{-- Start Button --}}
                @if ($tournament->status === 'draft')
                <form method="POST" action="{{ route('tournaments.start', $tournament) }}" class="mt-3">
                    @csrf
                    <button id="start-button"
                        class="px-4 py-2 bg-emerald-600 rounded-lg opacity-50 cursor-not-allowed" disabled>
                        Turnier starten
                    </button>
                </form>

                <p id="power-warning" class="text-red-400 mt-2">
                    ⚠ Teilnehmerzahl muss eine 2er-Potenz sein (2,4,8,16…)
                </p>
                @endif
            </div>

            {{-- TURNIERSIEGER --}}
            @if ($tournament->status === 'finished')
            @php
            $totalPlayers = $tournament->players->count();
            $totalRounds = log($totalPlayers, 2);

            $finalGame = $tournament->games->where('round', $totalRounds)->first();
            @endphp

            @if ($finalGame && $finalGame->winner)
            <div class="bg-emerald-700 p-6 rounded-xl text-center shadow-lg">
                <h2 class="text-2xl font-bold text-white">
                    🏆 Turniersieger
                </h2>
                <p class="text-3xl mt-3 font-extrabold text-white">
                    {{ $finalGame->winner->name }}
                </p>
            </div>
            @endif
            @endif

            @if ($tournament->status === 'draft')
            {{-- SPIELER HINZUFÜGEN (AJAX) --}}
            <div class="bg-gray-900 p-6 rounded-xl">
                <form id="player-form" class="flex space-x-4">
                    @csrf
                    <input type="text" id="player-name" name="name" placeholder="Spielername" autofocus
                        class="flex-1 rounded-lg bg-gray-700 text-white border-gray-600">

                    <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 rounded-lg">
                        Hinzufügen
                    </button>
                </form>
            </div>
            @endif

            @if ($tournament->status === 'draft')
            {{-- SPIELERLISTE --}}
            <div id="player-list" class="bg-gray-900 p-6 rounded-xl">
                <h3 class="text-lg text-gray-300 mb-4">
                    Teilnehmer (<span id="player-count">{{ $tournament->players->count() }}</span>)
                </h3>

                @foreach ($tournament->players->sortBy('seed') as $player)
                <div class="border-b border-gray-700 py-2 text-gray-200 flex justify-between">
                    <span>{{ $player->name }}</span>

                    @if ($player->seed)
                    <span class="text-gray-400">#{{ $player->seed }}</span>
                    @endif
                </div>
                @endforeach
            </div>
            @endif

            {{-- GRUPPENPHASE --}}

            @if ($tournament->status === 'group_running')

            @foreach ($tournament->groups as $group)
            <div class="text-yellow-400">
                Gruppe: {{ $group->name }}
            </div>
            <div class="text-blue-400">
                Spiele in Gruppe: {{ $group->games->count() }}
            </div>
            @php
            $table = app(\App\Services\GroupTableCalculator::class)->calculate($group);
            @endphp

            <div class="bg-gray-900 rounded-xl p-6 mb-8">
                <h3 class="text-lg text-gray-300 mb-4">
                    Gruppe {{ $group->name }}
                </h3>

                <table class="w-full text-sm text-left text-gray-300">
                    <thead class="text-xs uppercase text-gray-400 border-b border-gray-700">
                        <tr>
                            <th class="py-2">#</th>
                            <th>Spieler</th>
                            <th>Punkte</th>
                            <th>Differenz</th>
                        </tr>
                    </thead>
                    <tbody>

                        @foreach ($table as $index => $row)
                        <tr class="border-b border-gray-800 {{ $index < $tournament->group_advance_count ? 'bg-emerald-900/40' : '' }}">
                            <td class="py-2">{{ $index + 1 }}</td>
                            <td>
                                {{ $row['player']->name }}

                                @if ($index < $tournament->group_advance_count)
                                    <span class="ml-2 text-xs text-emerald-400 font-semibold">
                                        ✓ KO
                                    </span>
                                    @endif
                            </td>
                            <td>{{ $row['points'] }}</td>
                            <td
                                class="{{ $row['difference'] >= 0 ? 'text-emerald-400' : 'text-red-400' }}">
                                {{ $row['difference'] }}
                            </td>
                        </tr>
                        @endforeach

                    </tbody>
                </table>
            </div>
            <div class="mt-6 space-y-4">

                <h4 class="text-gray-400 text-sm uppercase tracking-wide">
                    Gruppenspiele
                </h4>

                @foreach ($group->games as $game)
                <div class="bg-gray-800 rounded-lg p-4 flex justify-between items-center">

                    <div class="flex items-center space-x-3 text-sm">
                        <span
                            class="{{ $game->winner_id === $game->player1_id ? 'text-emerald-400 font-semibold' : 'text-gray-300' }}">
                            {{ $game->player1->name }}
                        </span>

                        <span class="text-gray-500">:</span>

                        <span
                            class="{{ $game->winner_id === $game->player2_id ? 'text-emerald-400 font-semibold' : 'text-gray-300' }}">
                            {{ $game->player2->name }}
                        </span>
                    </div>

                    @if ($game->winner_id)
                    <div class="text-xs text-gray-400">
                        {{ $game->player1_score }} : {{ $game->player2_score }}
                    </div>
                    @else
                    <form method="POST" action="{{ route('games.updateScore', $game) }}"
                        class="flex items-center space-x-1">
                        @csrf
                        <input type="number" name="player1_score"
                            class="w-12 bg-gray-700 text-white rounded text-center text-sm">
                        <span class="text-gray-400">:</span>
                        <input type="number" name="player2_score"
                            class="w-12 bg-gray-700 text-white rounded text-center text-sm">
                        <button class="px-2 py-1 bg-emerald-600 text-xs rounded">✓</button>
                    </form>
                    @endif

                </div>
                @endforeach

            </div>
            @endforeach
            @php
            $allGroupGamesFinished = $tournament->groups
            ->flatMap(fn($g) => $g->games)
            ->whereNull('winner_id')
            ->count() === 0;
            @endphp

            @if ($tournament->status === 'group_running' && $allGroupGamesFinished)

            <form method="POST" action="{{ route('tournaments.startKo', $tournament) }}" class="mt-6">
                @csrf
                <button class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg">
                    🏁 KO-Phase starten
                </button>
            </form>

            @endif
            @endif

            {{-- KO RUNDEN --}}
            @if ($tournament->status === 'ko_running' || $tournament->status === 'finished')

            @php
            $koGames = $tournament->games
            ->where('is_group_match', 0);

            $groupedGames = $koGames
            ->sortBy('position')
            ->groupBy('round');

            $koPlayers = $koGames
            ->where('round', 1)
            ->flatMap(fn($g) => [$g->player1_id, $g->player2_id])
            ->unique()
            ->count();

            $totalRounds = $koPlayers > 0 ? log($koPlayers, 2) : 0;

            $koPlayers = $tournament->games
            ->where('round', 1)
            ->flatMap(fn($g) => [$g->player1_id, $g->player2_id])
            ->unique()
            ->count();

            $totalRounds = $koPlayers > 0 ? log($koPlayers, 2) : 0;

            @endphp

            <div class="overflow-x-auto">
                <div class="flex space-x-12 min-w-max">

                    @foreach ($groupedGames as $round => $games)
                    @php
                    $isSemifinalRound = $round == $totalRounds - 1;
                    @endphp
                    @php
                    $isThirdPlaceRound = $round == $totalRounds + 1;
                    @endphp

                    @continue($isThirdPlaceRound) @php
                    if ($round == $totalRounds + 1 && $tournament->has_third_place) {
                    $roundName = '🥉 Platz 3';
                    } else {
                    $roundsFromEnd = $totalRounds - $round + 1;

                    switch ($roundsFromEnd) {
                    case 1:
                    $roundName = '🏆 Finale';
                    break;
                    case 2:
                    $roundName = 'Halbfinale';
                    break;
                    case 3:
                    $roundName = 'Viertelfinale';
                    break;
                    case 4:
                    $roundName = 'Achtelfinale';
                    break;
                    default:
                    $roundName = 'Runde ' . $round;
                    }
                    }
                    @endphp

                    @php
                    $spacing = 60 * pow(2, $round - 1);
                    @endphp

                    <div class="flex flex-col" style="gap: {{ $spacing }}px;">
                        @php
                        $hasFinishedGame = $games->whereNotNull('winner_id')->count() > 0;
                        @endphp
                        <div class="flex items-center justify-between mb-3">

                            <h3 class="text-gray-300 font-semibold">
                                {{ $roundName }}
                            </h3>

                            @if (!$hasFinishedGame)
                            <form method="POST"
                                action="{{ route('round.updateBestOf', [$tournament, $round]) }}"
                                class="flex items-center space-x-2">
                                @csrf

                                <select name="best_of"
                                    class="bg-gray-700 text-white rounded text-sm px-2 py-1">
                                    @foreach ([1, 3, 5, 7, 9, 11] as $option)
                                    <option value="{{ $option }}"
                                        {{ $games->first()->best_of == $option ? 'selected' : '' }}>
                                        Bo{{ $option }}
                                    </option>
                                    @endforeach
                                </select>

                                <button
                                    class="px-2 py-1 bg-indigo-600 text-xs rounded hover:bg-indigo-700 transition">
                                    ✓
                                </button>
                            </form>
                            @endif

                        </div>
                        @foreach ($games as $game)
                        @php
                        $isFinalRound = $round == $totalRounds;
                        @endphp

                        <div
                            class="
        {{ !$isThirdPlaceRound && !$isFinalRound ? 'bracket-game' : '' }}
        {{ !$isThirdPlaceRound && !$isFinalRound && $loop->odd ? 'bracket-top' : '' }}
        {{ !$isThirdPlaceRound && !$isFinalRound && $loop->even ? 'bracket-bottom' : '' }}
        bg-gray-900 rounded-lg p-4 w-64 shadow
    ">
                            {{-- Spielernamen in einer Zeile --}}
                            <div class="flex justify-between items-center text-sm">

                                <span
                                    class="{{ $game->winner_id === $game->player1_id ? 'text-emerald-400 font-semibold' : 'text-gray-300' }}">
                                    {{ $game->player1->name ?? '—' }}
                                </span>

                                <span class="text-gray-500 px-2">:</span>

                                <span
                                    class="{{ $game->winner_id === $game->player2_id ? 'text-emerald-400 font-semibold' : 'text-gray-300' }}">
                                    {{ $game->player2->name ?? '—' }}
                                </span>

                            </div>

                            {{-- Ergebnis anzeigen --}}
                            @if ($game->winner_id)
                            <div class="text-xs text-gray-400 mt-2 text-center">
                                {{ $game->player1_score }} : {{ $game->player2_score }}
                                <span class="ml-2">(Bo{{ $game->best_of }})</span>
                            </div>
                            @endif

                            {{-- Score Eingabe --}}
                            @if (!$game->winner_id && $game->player1 && $game->player2)
                            <form method="POST" action="{{ route('games.updateScore', $game) }}"
                                class="mt-2 flex items-center justify-center space-x-1">
                                @csrf

                                <input type="number" name="player1_score" min="0"
                                    inputmode="numeric" pattern="[0-9]*"
                                    class="w-12 bg-gray-800 text-white rounded text-center text-sm">

                                <span class="text-gray-400">:</span>

                                <input type="number" name="player2_score" min="0"
                                    inputmode="numeric" pattern="[0-9]*"
                                    class="w-12 bg-gray-800 text-white rounded text-center text-sm">

                                <button class="ml-1 px-2 py-1 bg-emerald-600 text-xs rounded">
                                    ✓
                                </button>
                            </form>
                            @endif

                        </div>
                        @endforeach

                    </div>
                    @endforeach

                </div>
            </div>
            @if ($tournament->has_third_place)
            @php
            $thirdPlaceGames = $groupedGames->get($totalRounds + 1);
            @endphp

            @if ($thirdPlaceGames)
            <div class="mt-16 flex justify-center">
                <div class="bg-gray-800 border border-amber-500 rounded-xl p-6 w-72 text-center shadow-lg">

                    <h3 class="text-amber-400 font-semibold mb-4">
                        🥉 Spiel um Platz 3
                    </h3>

                    @foreach ($thirdPlaceGames as $game)
                    <div class="flex justify-between text-sm">

                        <span
                            class="{{ $game->winner_id === $game->player1_id ? 'text-amber-400 font-semibold' : 'text-gray-300' }}">
                            {{ $game->player1->name ?? '—' }}
                        </span>

                        <span class="text-gray-500 px-2">:</span>

                        <span
                            class="{{ $game->winner_id === $game->player2_id ? 'text-amber-400 font-semibold' : 'text-gray-300' }}">
                            {{ $game->player2->name ?? '—' }}
                        </span>

                    </div>

                    @if ($game->winner_id)
                    <div class="text-xs text-gray-400 mt-2">
                        {{ $game->player1_score }} : {{ $game->player2_score }}
                    </div>
                    @endif

                    @if (!$game->winner_id && $game->player1 && $game->player2)
                    <form method="POST" action="{{ route('games.updateScore', $game) }}"
                        class="mt-2 flex justify-center space-x-1">
                        @csrf

                        <input type="number" name="player1_score"
                            class="w-12 bg-gray-700 text-white rounded text-center text-sm">

                        <span class="text-gray-400">:</span>

                        <input type="number" name="player2_score"
                            class="w-12 bg-gray-700 text-white rounded text-center text-sm">

                        <button class="px-2 py-1 bg-amber-600 text-xs rounded">
                            ✓
                        </button>
                    </form>
                    @endif
                    @endforeach
                </div>
            </div>
            @endif
            @endif
            @endif

        </div>
    </div>

    {{-- JAVASCRIPT --}}
    <script>
        function checkPowerOfTwo() {
            const count = parseInt(document.getElementById('player-count').textContent);
            const startButton = document.getElementById('start-button');
            const warning = document.getElementById('power-warning');

            if (!startButton) return;

            const isPowerOfTwo = count > 0 && (count & (count - 1)) === 0;

            if (isPowerOfTwo) {
                startButton.disabled = false;
                startButton.classList.remove('opacity-50', 'cursor-not-allowed');
                warning.style.display = 'none';
            } else {
                startButton.disabled = true;
                startButton.classList.add('opacity-50', 'cursor-not-allowed');
                warning.style.display = 'block';
            }
        }

        checkPowerOfTwo();

        document.getElementById('player-form').addEventListener('submit', async function(e) {
            e.preventDefault();

            const input = document.getElementById('player-name');
            const name = input.value.trim();

            if (!name) return;

            const response = await fetch("{{ route('tournaments.players.store', $tournament) }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': "{{ csrf_token() }}",
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    name
                })
            });

            if (response.ok) {
                const data = await response.json();
                const list = document.getElementById('player-list');
                const counter = document.getElementById('player-count');

                const div = document.createElement('div');
                div.className = "border-b border-gray-700 py-2 text-gray-200";
                div.textContent = data.name;

                list.appendChild(div);
                counter.textContent = parseInt(counter.textContent) + 1;

                input.value = '';
                input.focus();

                checkPowerOfTwo();
            }
        });
    </script>

</x-app-layout>