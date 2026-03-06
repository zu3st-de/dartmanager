@extends('layouts.public')

@section('content')

<div class="max-w-7xl mx-auto px-6 py-8">

    <h2 class="text-3xl font-bold text-white mb-6">
        {{ $tournament->name }}
    </h2>
    @if($tournament->status === 'draft')

    <div id="draftOverlay"
        class="fixed inset-0 bg-black/90 flex flex-col items-center justify-center text-center z-50">
        <!-- Turniername -->
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

    <!-- Spielerfilter -->

    <select id="playerFilter"
        class="mb-6 w-full max-w-md bg-gray-900 border border-gray-700 rounded-lg px-4 py-2 text-gray-300">

        <option value="">Alle Spieler</option>

        @foreach($players as $player)

        <option value="{{ $player->id }}">
            {{ $player->name }}
        </option>

        @endforeach

    </select>


    <div id="followContent">

        <!-- GRUPPEN -->

        <div class="space-y-10">

            @foreach($groupData as $data)

            <div class="group-block border-b border-gray-700 pb-8"
                data-group="{{ $data['group']->id }}"
                data-players="{{ $data['group']->players->pluck('id')->join(',') }}">

                <h4 class="text-lg font-semibold text-white mb-4">
                    Gruppe {{ $data['group']->name }}
                </h4>


                <!-- Tabelle -->

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

                            @foreach($data['table'] as $index => $row)

                            @php
                            $isQualified = $index < $tournament->group_advance_count;
                                $isFirst = $index === 0;
                                $diff = $row['difference'];
                                @endphp

                                <tr class="{{ $isQualified ? 'bg-green-600/10' : '' }} border-t border-gray-800">

                                    <td class="px-2 py-2 font-mono {{ $isFirst ? 'text-yellow-400 font-bold' : '' }}">
                                        {{ $index + 1 }}
                                    </td>

                                    <td class="px-2 py-2 {{ $isQualified ? 'text-green-400' : '' }}">

                                        @if($isFirst)
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

                                    <td class="px-2 py-2 text-center font-mono
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
                <div class="mt-4 space-y-2 text-sm">

                    @if($data['lastGame'])

                    <div>
                        <strong>Letztes Spiel:</strong>

                        {{ $data['lastGame']->player1->name }}
                        {{ $data['lastGame']->player1_score }}
                        :
                        {{ $data['lastGame']->player2_score }}
                        {{ $data['lastGame']->player2->name }}

                    </div>

                    @endif


                    @if($data['currentGame'])

                    <div class="text-yellow-400 font-semibold">

                        Jetzt am Board:

                        {{ $data['currentGame']->player1?->name }}
                        vs
                        {{ $data['currentGame']->player2?->name }}

                    </div>

                    @endif


                    @if($data['nextGame'])

                    <div class="text-gray-400">

                        Nächstes Spiel:

                        {{ $data['nextGame']->player1?->name }}
                        vs
                        {{ $data['nextGame']->player2?->name }}

                    </div>

                    @endif

                </div>

                <!-- Spiele Button -->

                <button class="mt-4 text-sm px-3 py-1 rounded border border-gray-600 text-gray-300 hover:bg-gray-800"
                    onclick="toggleGroupGames({{ $data['group']->id }})">

                    Spiele anzeigen

                </button>


                <!-- Gruppenspiele -->

                <div id="groupGames{{ $data['group']->id }}" class="group-games mt-4 hidden">

                    @foreach($data['games'] as $match)

                    <div class="match-card border-b border-gray-800 py-2"
                        data-match="{{ $match->id }}"
                        data-player1="{{ $match->player1_id }}"
                        data-player2="{{ $match->player2_id }}">

                        <div class="flex justify-between text-sm">

                            <span class="{{ $match->winner_id == $match->player1_id ? 'text-green-400 font-semibold' : '' }}">
                                {{ $match->player1?->name }}
                            </span>

                            <span class="score text-gray-400">
                                {{ $match->player1_score }} : {{ $match->player2_score }}
                            </span>

                            <span class="{{ $match->winner_id == $match->player2_id ? 'text-green-400 font-semibold' : '' }}">
                                {{ $match->player2?->name }}
                            </span>

                        </div>

                    </div>

                    @endforeach

                </div>

            </div>

            @endforeach

        </div>


        <!-- KO PHASE -->

        @if($koRounds->count())

        <h4 class="text-xl font-semibold text-white mt-10 mb-4">
            KO Phase
        </h4>

        @php $totalRounds = $koRounds->count(); @endphp

        @foreach($koRounds as $roundNumber => $matches)

        <div class="ko-round mb-6">

            @php
            $currentRoundIndex = $loop->index;
            $roundFromEnd = $totalRounds - $currentRoundIndex;
            @endphp

            <h5 class="text-gray-400 mb-2">

                @switch($roundFromEnd)

                @case(1) Finale @break
                @case(2) Halbfinale @break
                @case(3) Viertelfinale @break
                @case(4) Achtelfinale @break

                @default Runde {{ $roundNumber }}

                @endswitch

            </h5>

            @foreach($matches as $match)

            <div class="match-card border-b border-gray-800 py-2"
                data-match="{{ $match->id }}"
                data-player1="{{ $match->player1_id }}"
                data-player2="{{ $match->player2_id }}">

                <div class="flex justify-between text-sm">

                    <span class="{{ $match->winner_id == $match->player1_id ? 'text-green-400 font-semibold' : '' }}">
                        {{ $match->player1?->name }}
                    </span>

                    <span class="score text-gray-400">
                        {{ $match->player1_score }} : {{ $match->player2_score }}
                    </span>

                    <span class="{{ $match->winner_id == $match->player2_id ? 'text-green-400 font-semibold' : '' }}">
                        {{ $match->player2?->name }}
                    </span>

                </div>

            </div>

            @endforeach

        </div>

        @endforeach

        @endif

    </div>

    @if($tournament->has_third_place && $thirdPlaceMatches->count())

    <div class="ko-round mb-6">

        <h5 class="text-gray-400 mb-2">Spiel um Platz 3</h5>

        @foreach($thirdPlaceMatches as $match)

        <div class="match-card"
            data-player1="{{ $match->player1_id }}"
            data-player2="{{ $match->player2_id }}">

            <div class="match-row">

                <span class="{{ $match->winner_id == $match->player1_id ? 'winner' : '' }}">
                    {{ $match->player1?->name }}
                </span>

                <span class="score">
                    {{ $match->player1_score }} : {{ $match->player2_score }}
                </span>

                <span class="{{ $match->winner_id == $match->player2_id ? 'winner' : '' }}">
                    {{ $match->player2?->name }}
                </span>

            </div>

        </div>

        @endforeach
    </div>
    @endif
    @if(isset($winner) && $winner)

    <div class="mt-12 text-center">

        <h3 class="text-xl font-bold mb-6">
            🏆 Siegertreppchen
        </h3>

        <div class="flex justify-center items-end gap-6">

            {{-- Platz 2 --}}
            <div class="text-center">

                <div class="bg-gray-700 px-6 py-4 rounded-t-lg">
                    🥈
                </div>

                <div class="bg-gray-600 px-6 py-2 font-semibold">
                    {{ $secondPlace->name ?? '—' }}
                </div>

                <div class="bg-gray-500 px-6 py-2 text-sm">
                    Platz 2
                </div>

            </div>


            {{-- Platz 1 --}}
            <div class="text-center scale-110">

                <div class="bg-yellow-500 px-8 py-6 rounded-t-lg text-2xl">
                    🏆
                </div>

                <div class="bg-yellow-400 px-8 py-3 font-bold text-black">
                    {{ $winner->name }}
                </div>

                <div class="bg-yellow-300 px-8 py-2 text-sm text-black">
                    Sieger
                </div>

            </div>


            {{-- Platz 3 --}}
            <div class="text-center">

                <div class="bg-orange-700 px-6 py-3 rounded-t-lg">
                    🥉
                </div>

                <div class="bg-orange-600 px-6 py-2 font-semibold">
                    {{ $thirdPlace->name ?? '—' }}
                </div>

                <div class="bg-orange-500 px-6 py-2 text-sm">
                    Platz 3
                </div>

            </div>

        </div>

    </div>

    @endif

</div>

@endsection


@push('scripts')

<script>
    const dice = ["⚀", "⚁", "⚂", "⚃", "⚄", "⚅"]

    function rollDice() {

        document.querySelectorAll(".dice-container span").forEach(d => {
            d.innerText = dice[Math.floor(Math.random() * 6)]
        })

    }

    setInterval(() => {

        document.querySelectorAll(".dice-container").forEach(container => {

            container.innerHTML = `
            <span>${dice[Math.floor(Math.random()*6)]}</span>
            <span>${dice[Math.floor(Math.random()*6)]}</span>
            <span>${dice[Math.floor(Math.random()*6)]}</span>
        `

        })

    }, 200)
    /* gespeicherte offene Gruppen */

    function getOpenGroups() {

        let stored = localStorage.getItem("openGroups")
        return stored ? JSON.parse(stored) : []

    }

    function saveOpenGroups(groups) {

        localStorage.setItem("openGroups", JSON.stringify(groups))

    }



    /* Spielerfilter */

    function applyPlayerFilter(player) {

        document.querySelectorAll(".group-block").forEach(group => {

            let players = group.dataset.players.split(",")

            group.style.display =
                player === "" || players.includes(player) ? "block" : "none"

        })


        document.querySelectorAll(".match-card").forEach(match => {

            let p1 = match.dataset.player1
            let p2 = match.dataset.player2

            match.style.display =
                player === "" || p1 == player || p2 == player ?
                "block" :
                "none"

        })

        document.querySelectorAll(".ko-round").forEach(round => {

            let visible = round.querySelectorAll(".match-card:not([style*='display: none'])")

            round.style.display = visible.length ? "block" : "none"

        })

    }



    /* Gruppenspiele toggeln */

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



    /* AJAX LIVE UPDATE */

    function refreshFollow() {

        fetch(window.location.pathname + "/data")
            .then(res => res.json())
            .then(data => {

                data.ko?.forEach?.(() => {})

                data.groups.forEach(group => {

                    group.games.forEach(match => {

                        let el = document.querySelector(`[data-match="${match.id}"]`)

                        if (!el) return

                        let score = el.querySelector(".score")

                        score.innerText =
                            (match.player1_score ?? "") +
                            " : " +
                            (match.player2_score ?? "")

                    })

                })

            })

    }

    setInterval(refreshFollow, 5000)



    /* Seite geladen */

    document.addEventListener("DOMContentLoaded", function() {

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
</script>

@endpush