{{-- ============================================================
   FOLLOW CONTENT (STABIL VERSION)
   ============================================================

   WICHTIG:
   - Wird von follow.blade.php verwendet
   - Muss exakt die Klassen enthalten (für JS Updates!)
   - Kein JS hier

   ============================================================ --}}



{{-- ============================================================
   GRUPPENPHASE
   ============================================================ --}}
<div class="space-y-10">

    @foreach ($groupData as $data)
        <div class="group-block border-b border-gray-700 pb-8" data-group="{{ $data['group']->id }}"
            data-players="{{ $data['group']->players->pluck('id')->join(',') }}">

            <h4 class="text-lg font-semibold text-white mb-4">
                Gruppe {{ $data['group']->name }}
            </h4>


            {{-- =========================
            TABELLE
        ========================== --}}
            <div class="overflow-hidden rounded-lg border border-gray-700">

                <table class="w-full text-sm">

                    <thead class="bg-gray-800 text-gray-400 uppercase text-xs">
                        <tr>
                            <th>#</th>
                            <th>Spieler</th>
                            <th>Sp</th>
                            <th>S</th>
                            <th>N</th>
                            <th>Diff</th>
                            <th>Pkt</th>
                        </tr>
                    </thead>

                    <tbody class="group-table" data-group="{{ $data['group']->id }}">

                        @foreach ($data['table'] as $index => $row)
                            @php
                                $diff = $row['difference'];
                            @endphp

                            <tr class="group-row border-t border-gray-800" data-player="{{ $row['player']->id }}"
                                data-group="{{ $data['group']->id }}">

                                {{-- Platz --}}
                                <td
                                    class="rank px-2 py-2 font-mono {{ $index === 0 ? 'text-yellow-400 font-bold' : '' }}">
                                    {{ $index + 1 }}
                                </td>

                                {{-- Spieler --}}
                                <td
                                    class="name px-2 py-2 {{ $index < $tournament->group_advance_count ? 'text-green-400' : '' }}">
                                    @if ($index === 0)
                                        🏆
                                    @endif
                                    {{ $row['player']->name }}
                                </td>

                                {{-- Stats --}}
                                <td class="played text-center">{{ $row['played'] }}</td>
                                <td class="wins text-center text-green-400">{{ $row['wins'] }}</td>
                                <td class="losses text-center text-red-400">{{ $row['losses'] }}</td>

                                {{-- Diff --}}
                                <td
                                    class="diff text-center font-mono
                                {{ $diff > 0 ? 'text-green-400' : ($diff < 0 ? 'text-red-400' : 'text-gray-400') }}">
                                    {{ $diff > 0 ? '+' : '' }}{{ $diff }}
                                </td>

                                {{-- Punkte --}}
                                <td class="points text-center font-bold text-white">
                                    {{ $row['points'] }}
                                </td>

                            </tr>
                        @endforeach

                    </tbody>

                </table>
            </div>


            {{-- =========================
            MATCH INFO
        ========================== --}}
            <div class="mt-4 space-y-2 text-sm">

                @if ($data['lastGame'])
                    <div class="last-game text-sm" data-group="{{ $data['group']->id }}">

                        @if ($data['lastGame'])
                            <strong>Letztes Spiel:</strong>

                            {{ $data['lastGame']->player1->name }}
                            {{ $data['lastGame']->player1_score }}
                            :
                            {{ $data['lastGame']->player2_score }}
                            {{ $data['lastGame']->player2->name }}
                        @endif

                    </div>
                @endif


                @if ($data['currentGame'])
                    <div class="current-game text-yellow-400 font-semibold" data-group="{{ $data['group']->id }}">

                        @if ($data['currentGame'])
                            Jetzt am Board:
                            {{ $data['currentGame']->player1?->name }}
                            vs
                            {{ $data['currentGame']->player2?->name }}
                        @endif

                    </div>
                @endif


                @if ($data['nextGame'])
                    <div class="next-game text-gray-400" data-group="{{ $data['group']->id }}">

                        @if ($data['nextGame'])
                            Nächstes Spiel:
                            {{ $data['nextGame']->player1?->name }}
                            vs
                            {{ $data['nextGame']->player2?->name }}
                        @endif

                    </div>
                @endif

            </div>


            {{-- =========================
            TOGGLE BUTTON
        ========================== --}}
            <button id="toggleBtn{{ $data['group']->id }}" onclick="toggleGroupGames({{ $data['group']->id }})"
                class="mt-4 text-sm px-3 py-1 border border-gray-600 rounded text-gray-300 hover:bg-gray-800">

                Spiele anzeigen
            </button>


            {{-- =========================
            MATCH LISTE
        ========================== --}}
            <div id="groupGames{{ $data['group']->id }}" class="group-games mt-4 hidden">

                @foreach ($data['games'] as $match)
                    <div class="match-card border-b border-gray-800 py-2" data-match="{{ $match->id }}"
                        data-player1="{{ $match->player1_id }}" data-player2="{{ $match->player2_id }}">

                        <div class="flex justify-between text-sm">

                            <span
                                class="{{ $match->winner_id == $match->player1_id ? 'text-green-400 font-semibold' : '' }}">
                                {{ $match->player1?->name }}
                            </span>

                            <span class="score text-gray-400">
                                {{ $match->player1_score }} : {{ $match->player2_score }}
                            </span>

                            <span
                                class="{{ $match->winner_id == $match->player2_id ? 'text-green-400 font-semibold' : '' }}">
                                {{ $match->player2?->name }}
                            </span>

                        </div>

                    </div>
                @endforeach

            </div>

        </div>
    @endforeach

</div>



{{-- ============================================================
   KO PHASE
   ============================================================ --}}
@if ($koRounds->count())

    <h4 class="text-xl font-semibold text-white mt-10 mb-4">
        KO Phase
    </h4>

    @php $totalRounds = $koRounds->count(); @endphp

    @foreach ($koRounds as $roundNumber => $matches)
        @php
            $currentRoundIndex = $loop->index;
            $roundFromEnd = $totalRounds - $currentRoundIndex;
        @endphp


        {{-- Platz 3 --}}
        @if ($roundFromEnd === 1 && $tournament->has_third_place && $thirdPlaceMatches->count())
            <div class="ko-round mb-6">

                <h5 class="text-gray-400 mb-2">
                    Spiel um Platz 3
                </h5>

                @foreach ($thirdPlaceMatches as $match)
                    <div class="match-card border-b border-gray-800 py-2" data-match="{{ $match->id }}"
                        data-player1="{{ $match->player1_id }}" data-player2="{{ $match->player2_id }}">

                        <div class="flex justify-between text-sm">

                            <span
                                class="{{ $match->winner_id == $match->player1_id ? 'text-green-400 font-semibold' : '' }}">
                                @if ($thirdPlace && $match->player1_id === $thirdPlace->id)
                                    🥉
                                @endif
                                {{ $match->player1->name ?? formatSource($match->player1_source) }}
                            </span>

                            <span class="score text-gray-400">
                                {{ $match->player1_score }} : {{ $match->player2_score }}
                            </span>

                            <span
                                class="{{ $match->winner_id == $match->player2_id ? 'text-green-400 font-semibold' : '' }}">
                                @if ($thirdPlace && $match->player2_id === $thirdPlace->id)
                                    🥉
                                @endif
                                {{ $match->player2->name ?? formatSource($match->player2_source) }}
                            </span>

                        </div>

                    </div>
                @endforeach

            </div>
        @endif


        {{-- Normale KO Runde --}}
        <div class="ko-round mb-6">

            <h5 class="text-gray-400 mb-2">
                {{ koRoundName($matches->count()) }}
            </h5>

            @foreach ($matches as $match)
                <div class="match-card border-b border-gray-800 py-2" data-match="{{ $match->id }}"
                    data-player1="{{ $match->player1_id }}" data-player2="{{ $match->player2_id }}">

                    <div class="flex justify-between text-sm">

                        <span
                            class="{{ $match->winner_id == $match->player1_id ? 'text-green-400 font-semibold' : '' }}">

                            {{-- 🏆 Platzierungen --}}
                            @if ($roundFromEnd === 1)
                                @if ($winner && $match->player1_id === $winner->id)
                                    🥇
                                @elseif($secondPlace && $match->player1_id === $secondPlace->id)
                                    🥈
                                @endif
                            @endif
                            {{ $match->player1->name ?? formatSource($match->player1_source, $roundFromEnd) }}

                        </span>

                        <span class="score text-gray-400">
                            {{ $match->player1_score }} : {{ $match->player2_score }}
                        </span>

                        <span
                            class="{{ $match->winner_id == $match->player2_id ? 'text-green-400 font-semibold' : '' }}">

                            {{-- 🏆 Platzierungen --}}
                            @if ($roundFromEnd === 1)
                                @if ($winner && $match->player2_id === $winner->id)
                                    🥇
                                @elseif($secondPlace && $match->player2_id === $secondPlace->id)
                                    🥈
                                @endif
                            @endif
                            {{ $match->player2->name ?? formatSource($match->player2_source, $roundFromEnd) }}

                        </span>

                    </div>

                </div>
            @endforeach

        </div>
    @endforeach

@endif
{{-- ============================================================
PODIUM
============================================================ --}}
<div id="victoryOverlay" class="victory-overlay">

        <div class="victory-content">

            <div class="victory-title">
                🏆 Turniersieger 🏆
            </div>

            <div class="winner-name" id="victoryWinnerName">
                {{ $winner?->name }}
            </div>

            <div class="podium">

                {{-- 🥈 --}}
                <div class="place second">
                    <div class="name" id="victorySecondName">{{ $secondPlace?->name }}</div>
                    <div class="step">🥈</div>
                </div>

                {{-- 🥇 --}}
                <div class="place first">
                    <div class="name" id="victoryFirstName">{{ $winner?->name }}</div>
                    <div class="step">🏆</div>
                </div>

                {{-- 🥉 --}}
                <div class="place third">
                    <div class="name" id="victoryThirdName">{{ $thirdPlace?->name }}</div>
                    <div class="step">🥉</div>
                </div>

            </div>

        </div>

    </div>
