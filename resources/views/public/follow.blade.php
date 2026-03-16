@extends('layouts.public')

@section('content')

    @php

        /**
         * -------------------------------------------------------------
         * SOURCE FORMATIERUNG FÜR KO MATCHES
         * -------------------------------------------------------------
         *
         * KO Spiele werden in der DB häufig mit Quellen gespeichert wie:
         *
         * W1  = Winner Spiel 1
         * L2  = Loser Halbfinale 2
         * A1  = 1. Platz Gruppe A
         *
         * Diese Funktion übersetzt diese Kurzform in lesbare Texte
         * für Zuschauer auf der Follow-Seite.
         */
        function formatSource($source, $roundFromEnd = null)
        {
            if (!$source) {
                return '—';
            }

            if (preg_match('/([A-Z]+)(\d+)/', $source, $m)) {
                $type = $m[1];
                $num = $m[2];

                // Gewinner vorheriger KO Runde
                if ($type === 'W') {
                    if ($roundFromEnd === 3) {
                        return "Sieger {$num}. Achtelfinale";
                    }

                    if ($roundFromEnd === 2) {
                        return "Sieger {$num}. Viertelfinale";
                    }

                    if ($roundFromEnd === 1) {
                        return "Sieger {$num}. Halbfinale";
                    }

                    return "Sieger Spiel {$num}";
                }

                // Verlierer Halbfinale → Spiel um Platz 3
                if ($type === 'L') {
                    return "Verlierer {$num}. Halbfinale";
                }

                // Gruppenergebnis
                return $num . '. Gruppe ' . $type;
            }

            return $source;
        }

        /**
         * -------------------------------------------------------------
         * AUTOMATISCHE KO RUNDEN BENENNUNG
         * -------------------------------------------------------------
         *
         * Bestimmt den Namen der KO Runde anhand der Match Anzahl.
         *
         * Beispiel:
         * 1 Match  -> Finale
         * 2 Matches -> Halbfinale
         * 4 Matches -> Viertelfinale
         */
        function koRoundName($matchCount)
        {
            $players = $matchCount * 2;

            return match ($players) {
                2 => 'Finale',
                4 => 'Halbfinale',
                8 => 'Viertelfinale',
                16 => 'Achtelfinale',
                32 => 'Sechzehntelfinale',
                default => "Runde der {$players}",
            };
        }

    @endphp


    <div class="max-w-7xl mx-auto px-6 py-8">

        {{-- ============================================================
   TURNIER NAME
============================================================ --}}

        <h2 class="text-3xl font-bold text-white mb-6">
            {{ $tournament->name }}
        </h2>


        {{-- ============================================================
   DRAFT OVERLAY (WENN TURNIER NOCH NICHT GESTARTET)
============================================================ --}}

        @if ($tournament->status === 'draft')
            <div id="draftOverlay"
                class="fixed inset-0 bg-black/90 flex flex-col items-center justify-center text-center z-50">

                <div class="text-4xl font-bold mb-6">
                    {{ $tournament->name }}
                </div>

                <div class="text-3xl font-bold mb-6">
                    Turnier startet in wenigen Augenblicken
                </div>

                <div class="text-lg text-gray-300 mb-10">
                    Teilnehmer werden ausgelost
                </div>

                <div class="dice-container text-6xl">
                    🎲 🎲 🎲
                </div>

            </div>
        @endif


        {{-- ============================================================
   SPIELER FILTER
   Ermöglicht nur Spiele eines Spielers anzuzeigen
============================================================ --}}

        <select id="playerFilter"
            class="mb-6 w-full max-w-md bg-gray-900 border border-gray-700 rounded-lg px-4 py-2 text-gray-300">

            <option value="">Alle Spieler</option>

            @foreach ($players as $player)
                <option value="{{ $player->id }}">
                    {{ $player->name }}
                </option>
            @endforeach

        </select>



        <div id="followContent">


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


                        {{-- ------------------------------------------------------------
   GRUPPENTABELLE
------------------------------------------------------------ --}}

                        <div class="overflow-hidden rounded-lg border border-gray-700">

                            <table class="w-full text-sm">

                                <thead class="bg-gray-800 text-gray-400 uppercase text-xs tracking-wider">

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


                                <tbody>

                                    @foreach ($data['table'] as $index => $row)
                                        @php
                                            $isQualified = $index < $tournament->group_advance_count;
                                            $isFirst = $index === 0;
                                            $diff = $row['difference'];
                                        @endphp


                                        <tr class="{{ $isQualified ? 'bg-green-600/10' : '' }} border-t border-gray-800">

                                            <td
                                                class="px-2 py-2 font-mono {{ $isFirst ? 'text-yellow-400 font-bold' : '' }}">
                                                {{ $index + 1 }}
                                            </td>

                                            <td class="px-2 py-2 {{ $isQualified ? 'text-green-400' : '' }}">

                                                @if ($isFirst)
                                                    🏆
                                                @endif

                                                {{ $row['player']->name }}

                                            </td>

                                            <td class="px-2 py-2 text-center">
                                                {{ $row['played'] }}
                                            </td>

                                            <td class="px-2 py-2 text-center text-green-400">
                                                {{ $row['wins'] }}
                                            </td>

                                            <td class="px-2 py-2 text-center text-red-400">
                                                {{ $row['losses'] }}
                                            </td>

                                            <td
                                                class="px-2 py-2 text-center font-mono
{{ $diff > 0 ? 'text-green-400' : ($diff < 0 ? 'text-red-400' : 'text-gray-400') }}">

                                                {{ $diff > 0 ? '+' : '' }}{{ $diff }}

                                            </td>

                                            <td class="px-2 py-2 text-center font-bold text-white">
                                                {{ $row['points'] }}
                                            </td>

                                        </tr>
                                    @endforeach

                                </tbody>

                            </table>

                        </div>



                        {{-- ------------------------------------------------------------
   LETZTES / AKTUELLES / NÄCHSTES SPIEL
------------------------------------------------------------ --}}

                        <div class="mt-4 space-y-2 text-sm">

                            @if ($data['lastGame'])
                                <div>
                                    <strong>Letztes Spiel:</strong>

                                    {{ $data['lastGame']->player1->name }}
                                    {{ $data['lastGame']->player1_score }}
                                    :
                                    {{ $data['lastGame']->player2_score }}
                                    {{ $data['lastGame']->player2->name }}

                                </div>
                            @endif


                            @if ($data['currentGame'])
                                <div class="text-yellow-400 font-semibold">

                                    Jetzt am Board:

                                    {{ $data['currentGame']->player1?->name }}
                                    vs
                                    {{ $data['currentGame']->player2?->name }}

                                </div>
                            @endif


                            @if ($data['nextGame'])
                                <div class="text-gray-400">

                                    Nächstes Spiel:

                                    {{ $data['nextGame']->player1?->name }}
                                    vs
                                    {{ $data['nextGame']->player2?->name }}

                                </div>
                            @endif

                        </div>



                        {{-- ------------------------------------------------------------
   BUTTON ZUM AUFKLAPPEN DER GRUPPENSPIELE
------------------------------------------------------------ --}}

                        <button
                            class="mt-4 text-sm px-3 py-1 rounded border border-gray-600 text-gray-300 hover:bg-gray-800"
                            onclick="toggleGroupGames({{ $data['group']->id }})">

                            Spiele anzeigen

                        </button>



                        {{-- ------------------------------------------------------------
   GRUPPENSPIELE LISTE
------------------------------------------------------------ --}}

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



                    {{-- Spiel um Platz 3 vor Finale --}}
                    @if ($roundFromEnd === 1 && $tournament->has_third_place && $thirdPlaceMatches->count())
                        <div class="ko-round mb-6">

                            <h5 class="text-gray-400 mb-2">Spiel um Platz 3</h5>

                            @foreach ($thirdPlaceMatches as $match)
                                <div class="match-card border-b border-gray-800 py-2">

                                    <div class="flex justify-between text-sm">

                                        <span>{{ $match->player1->name ?? formatSource($match->player1_source) }}</span>

                                        <span class="score text-gray-400">
                                            {{ $match->player1_score }} : {{ $match->player2_score }}
                                        </span>

                                        <span>{{ $match->player2->name ?? formatSource($match->player2_source) }}</span>

                                    </div>

                                </div>
                            @endforeach

                        </div>
                    @endif



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
                                        {{ $match->player1->name ?? formatSource($match->player1_source, $roundFromEnd) }}
                                    </span>

                                    <span class="score text-gray-400">
                                        {{ $match->player1_score }} : {{ $match->player2_score }}
                                    </span>

                                    <span
                                        class="{{ $match->winner_id == $match->player2_id ? 'text-green-400 font-semibold' : '' }}">
                                        {{ $match->player2->name ?? formatSource($match->player2_source, $roundFromEnd) }}
                                    </span>

                                </div>

                            </div>
                        @endforeach

                    </div>
                @endforeach
            @endif



        </div>
    @endsection
    @push('scripts')
        <script>
            /* ---------------------------
                                           LocalStorage für Gruppen
                                        --------------------------- */

            function getOpenGroups() {

                let stored = localStorage.getItem("openGroups")
                return stored ? JSON.parse(stored) : []

            }

            function saveOpenGroups(groups) {

                localStorage.setItem("openGroups", JSON.stringify(groups))

            }


            /* ---------------------------
               Gruppenspiele anzeigen
            --------------------------- */

            window.toggleGroupGames = function(groupId) {

                let el = document.getElementById("groupGames" + groupId)

                if (!el) return

                el.classList.toggle("hidden")

                let openGroups = getOpenGroups()

                if (!el.classList.contains("hidden")) {

                    if (!openGroups.includes(groupId)) openGroups.push(groupId)

                } else {

                    openGroups = openGroups.filter(id => id != groupId)

                }

                saveOpenGroups(openGroups)

            }


            /* ---------------------------
               Spielerfilter
            --------------------------- */

            function applyPlayerFilter(player) {

                /* Gruppen filtern */

                document.querySelectorAll(".group-block").forEach(group => {

                    let players = group.dataset.players.split(",")

                    group.style.display =
                        player === "" || players.includes(player) ?
                        "block" :
                        "none"

                })


                /* Matches filtern */

                document.querySelectorAll(".match-card").forEach(match => {

                    let p1 = match.dataset.player1
                    let p2 = match.dataset.player2

                    match.style.display =
                        player === "" || p1 == player || p2 == player ?
                        "block" :
                        "none"

                })


                /* leere KO Runden verstecken */

                document.querySelectorAll(".ko-round").forEach(round => {

                    let visible = round.querySelectorAll(".match-card:not([style*='display: none'])")

                    round.style.display = visible.length ? "block" : "none"

                })

            }


            /* ---------------------------
               Seite geladen
            --------------------------- */

            document.addEventListener("DOMContentLoaded", function() {

                /* Spielerfilter */

                let filter = document.getElementById("playerFilter")

                if (filter) {

                    let savedPlayer = localStorage.getItem("followPlayerFilter")

                    if (savedPlayer) {

                        filter.value = savedPlayer
                        applyPlayerFilter(savedPlayer)

                    }

                    filter.addEventListener("change", function() {

                        let player = this.value

                        localStorage.setItem("followPlayerFilter", player)

                        applyPlayerFilter(player)

                    })

                }


                /* offene Gruppen wieder öffnen */

                let openGroups = getOpenGroups()

                openGroups.forEach(groupId => {

                    let el = document.getElementById("groupGames" + groupId)

                    if (el) el.classList.remove("hidden")

                })

            })

            function refreshFollow() {

                fetch(window.location.pathname)
                    .then(res => res.text())
                    .then(html => {

                        let parser = new DOMParser()
                        let doc = parser.parseFromString(html, "text/html")

                        let newContent = doc.querySelector("#followContent")

                        document.querySelector("#followContent").innerHTML =
                            newContent.innerHTML

                    })

            }

            setInterval(refreshFollow, 5000)
        </script>
    @endpush
