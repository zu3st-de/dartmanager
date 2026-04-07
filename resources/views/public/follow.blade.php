@extends('layouts.public')

@section('content')
    @php
        function formatSource($source, $roundFromEnd = null)
        {
            if (!$source) {
                return '—';
            }

            if (preg_match('/([A-Z]+)(\d+)/', $source, $m)) {
                $type = $m[1];
                $num = $m[2];

                if ($type === 'W') {
                    return match ($roundFromEnd) {
                        3 => "Sieger {$num}. Achtelfinale",
                        2 => "Sieger {$num}. Viertelfinale",
                        1 => "Sieger {$num}. Halbfinale",
                        default => "Sieger Spiel {$num}",
                    };
                }

                if ($type === 'L') {
                    return "Verlierer {$num}. Halbfinale";
                }

                return $num . '. Gruppe ' . $type;
            }

            return $source;
        }

        function koRoundName($matchCount)
        {
            $players = $matchCount * 2;

            return match ($players) {
                2 => 'Finale',
                4 => 'Halbfinale',
                8 => 'Viertelfinale',
                16 => 'Achtelfinale',
                default => "Runde der {$players}",
            };
        }
    @endphp


    <div class="max-w-7xl mx-auto px-6 py-8">

        {{-- TURNIERNAME --}}
        <h2 class="text-3xl font-bold text-white mb-6">
            {{ $tournament->name }}
        </h2>


        {{-- SPIELER FILTER --}}
        @if ($tournament->status !== 'draft')
            <select id="playerFilter"
                class="mb-6 w-full max-w-md bg-gray-900 border border-gray-700 rounded-lg px-4 py-2 text-gray-300">

                <option value="">Alle Spieler</option>

                @foreach ($players as $player)
                    <option value="{{ $player->id }}">
                        {{ $player->name }}
                    </option>
                @endforeach
            </select>
        @endif

        {{-- CONTENT --}}
        <div id="followContent">

            {{-- 🟡 TURNIER STARTET DEMNÄCHST --}}
            @if ($tournament->status === 'draft')
                @include('public.partials.draft-animation', [
                    'players' => $players,
                ])
            @else
                @include(
                    'public.partials.follow-content',
                    compact('groupData', 'koRounds', 'thirdPlaceMatches', 'tournament'))
            @endif

        </div>
    </div>
@endsection



@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>

    <script>
        /* ============================================================
                            LOCAL STORAGE
                            ========================================================== */
        function getOpenGroups() {
            return JSON.parse(localStorage.getItem("openGroups") || "[]");
        }

        function saveOpenGroups(groups) {
            localStorage.setItem("openGroups", JSON.stringify(groups));
        }


        /* ============================================================
           TOGGLE GROUP GAMES
        ============================================================ */

        window.toggleGroupGames = function(groupId) {

            let el = document.getElementById("groupGames" + groupId);
            let btn = document.getElementById("toggleBtn" + groupId);

            if (!el) return;

            el.classList.toggle("hidden");

            let openGroups = getOpenGroups();

            if (!el.classList.contains("hidden")) {

                if (!openGroups.includes(groupId))
                    openGroups.push(groupId);

                if (btn) btn.textContent = "Spiele ausblenden";

            } else {

                openGroups = openGroups.filter(id => id != groupId);

                if (btn) btn.textContent = "Spiele anzeigen";
            }

            saveOpenGroups(openGroups);
        };


        /* ============================================================
           PLAYER FILTER
        ============================================================ */

        function applyPlayerFilter(player) {

            document.querySelectorAll(".group-block").forEach(group => {

                let players = group.dataset.players.split(",");

                group.style.display =
                    player === "" || players.includes(player) ?
                    "block" :
                    "none";
            });

            document.querySelectorAll(".match-card").forEach(match => {

                let p1 = match.dataset.player1;
                let p2 = match.dataset.player2;

                match.style.display =
                    player === "" || p1 == player || p2 == player ?
                    "block" :
                    "none";
            });
        }


        /* ============================================================
           MATCH UPDATE
        ============================================================ */

        function updateMatch(match) {

            let el = document.querySelector(`[data-match="${match.id}"]`);
            if (!el) return;

            let scoreEl = el.querySelector(".score");

            let newScore = `${match.player1_score ?? ""} : ${match.player2_score ?? ""}`;

            if (scoreEl.textContent !== newScore) {

                scoreEl.textContent = newScore;

                el.classList.add("updated");
                setTimeout(() => el.classList.remove("updated"), 400);
            }
        }


        /* ============================================================
           TABLE UPDATE (STABIL VERSION)
        ============================================================ */
        const GROUP_ADVANCE_COUNT = {{ (int) ($tournament->group_advance_count ?? 0) }};

        function updateTable(groups) {

            groups.forEach(group => {

                let tbody = document.querySelector(
                    `.group-table[data-group="${group.group.id}"]`
                );

                if (!tbody) return;

                // 🔥 bestehende rows mappen
                let rows = {};
                tbody.querySelectorAll(".group-row").forEach(tr => {
                    rows[tr.dataset.player] = tr;
                });

                // 🔥 Fragment (wird NICHT gerendert!)
                let fragment = document.createDocumentFragment();

                group.table.forEach((row, newIndex) => {

                    let tr = rows[row.player.id];
                    if (!tr) return;

                    let rankEl = tr.querySelector(".rank");
                    let nameEl = tr.querySelector(".name");

                    // 👉 Werte setzen
                    rankEl.textContent = newIndex + 1;

                    rankEl.className = "rank px-2 py-2 font-mono";
                    nameEl.className = "name px-2 py-2";

                    if (newIndex === 0) {
                        rankEl.classList.add("text-yellow-400", "font-bold");
                        nameEl.textContent = "🏆 " + row.player.name;
                    } else {
                        nameEl.textContent = row.player.name;
                    }

                    if (newIndex < GROUP_ADVANCE_COUNT) {
                        nameEl.classList.add("text-green-400");
                    }

                    tr.querySelector(".played").textContent = row.played;
                    tr.querySelector(".wins").textContent = row.wins;
                    tr.querySelector(".losses").textContent = row.losses;
                    tr.querySelector(".points").textContent = row.points;

                    let diff = row.difference;
                    let diffEl = tr.querySelector(".diff");

                    diffEl.textContent = (diff > 0 ? "+" : "") + diff;

                    diffEl.className =
                        "diff text-center " +
                        (diff > 0 ?
                            "text-green-400" :
                            diff < 0 ?
                            "text-red-400" :
                            "text-gray-400");

                    // 👉 in Fragment packen (nicht ins DOM!)
                    fragment.appendChild(tr);
                });

                // 🔥 EIN EINZIGER DOM UPDATE
                tbody.appendChild(fragment);
            });
        }

        /* ============================================================
           GROUP + KO
        ============================================================ */

        function updateGroups(groups) {
            groups.forEach(group => {
                group.games.forEach(updateMatch);
            });
        }

        async function updateKoFull() {

            const res = await fetch(window.location.pathname);
            const html = await res.text();

            let parser = new DOMParser();
            let doc = parser.parseFromString(html, "text/html");

            let newKo = doc.querySelectorAll(".ko-round");
            let currentKo = document.querySelectorAll(".ko-round");

            currentKo.forEach((el, i) => {
                if (newKo[i]) {
                    el.innerHTML = newKo[i].innerHTML;
                }
            });
        }


        /* ============================================================
           RESTORE UI
        ============================================================ */

        function restoreUI() {

            // offene Gruppen
            getOpenGroups().forEach(groupId => {

                let el = document.getElementById("groupGames" + groupId);
                let btn = document.getElementById("toggleBtn" + groupId);

                if (el) el.classList.remove("hidden");
                if (btn) btn.textContent = "Spiele ausblenden";
            });

            // Filter
            let saved = localStorage.getItem("followPlayerFilter");

            if (saved) {
                let filter = document.getElementById("playerFilter");
                if (filter) filter.value = saved;

                applyPlayerFilter(saved);
            }
        }


        /* ============================================================
           REFRESH
        ============================================================ */
        const INITIAL_PODIUM_READY = {{ !empty($podiumReady) ? 'true' : 'false' }};
        let victoryShown = localStorage.getItem("victoryShown") === "true";
        async function refreshFollow() {

            try {
                const res = await fetch(window.location.pathname + "/data");
                const data = await res.json();

                updateGroups(data.groups);
                await updateKoFull();
                updateTable(data.groups);
                updateCurrentGames(data.groups);

                if (data.podium_ready && !victoryShown) {

                    victoryShown = true;
                    localStorage.setItem("victoryShown", "true");
                    applyPodiumData(data.podium);

                    setTimeout(() => {
                        showVictoryOverlay();
                    }, 800);
                }
                // 🔄 RESET wenn Turnier wieder offen ist
                if (!data.podium_ready) {
                    localStorage.removeItem("victoryShown");
                    victoryShown = false;
                }

                restoreUI();

            } catch (e) {
                console.error("Refresh Fehler", e);
            }
        }


        /* ============================================================
           INIT
        ============================================================ */

        document.addEventListener("DOMContentLoaded", function() {

            restoreUI();

            let filter = document.getElementById("playerFilter");

            if (filter) {
                filter.addEventListener("change", function() {

                    let player = this.value;

                    localStorage.setItem("followPlayerFilter", player);
                    applyPlayerFilter(player);
                });
            }
            // 🔥 INITIAL CHECK (falls Seite schon finished lädt)
            if (INITIAL_PODIUM_READY) {
                applyPodiumData({
                    winner: @json($winner?->name),
                    second_place: @json($secondPlace?->name),
                    third_place: @json($thirdPlace?->name),
                });

                setTimeout(() => {
                    showVictoryOverlay();
                }, 1000);
            }

            setInterval(refreshFollow, 5000);
        });

        function updateCurrentGames(groups) {

            groups.forEach(group => {

                let games = group.games;

                // 🔥 sortieren (wichtig!)
                games.sort((a, b) => a.id - b.id);

                let finished = games
                    .filter(g => g.winner_id !== null)
                    .sort((a, b) => new Date(b.updated_at) - new Date(a.updated_at));

                let open = games
                    .filter(g => g.winner_id === null)
                    .sort((a, b) => a.id - b.id);

                let last = finished[0] || null; // 🔥 jetzt korrekt!
                let current = open[0] || null;
                let next = open[1] || null;

                let lastEl = document.querySelector(
                    `.last-game[data-group="${group.group.id}"]`
                );

                let currentEl = document.querySelector(
                    `.current-game[data-group="${group.group.id}"]`
                );

                let nextEl = document.querySelector(
                    `.next-game[data-group="${group.group.id}"]`
                );

                // =====================
                // LAST GAME
                // =====================
                if (lastEl) {
                    if (last && last.player1 && last.player2) {

                        let p1Win = last.winner_id === last.player1_id;
                        let p2Win = last.winner_id === last.player2_id;

                        let p1Class = p1Win ? "text-green-400 font-semibold" : "";
                        let p2Class = p2Win ? "text-green-400 font-semibold" : "";

                        lastEl.innerHTML =
                            `<strong>Letztes Spiel:</strong>
             <span class="${p1Class}">
                ${last.player1.name}
             </span>
             ${last.player1_score ?? ''}
             :
             ${last.player2_score ?? ''}
             <span class="${p2Class}">
                ${last.player2.name}
             </span>`;
                    } else {
                        lastEl.innerHTML = "";
                    }
                }

                // =====================
                // CURRENT GAME
                // =====================
                if (currentEl) {

                    if (current) {

                        let text =
                            `Jetzt am Board: ${current.player1?.name ?? ''} vs ${current.player2?.name ?? ''}`;

                        // 🔥 nur ändern wenn nötig
                        if (currentEl.textContent !== text) {

                            currentEl.innerHTML = text;

                            currentEl.classList.add("live");

                            setTimeout(() => {
                                currentEl.classList.remove("live");
                            }, 1000);


                        }

                    } else {
                        currentEl.innerHTML = "";
                        currentEl.classList.remove("live");
                    }
                }

                // =====================
                // NEXT GAME
                // =====================
                if (nextEl) {
                    if (next) {
                        nextEl.innerHTML =
                            `Nächstes Spiel: ${next.player1?.name ?? ''} vs ${next.player2?.name ?? ''}`;
                    } else {
                        nextEl.innerHTML = "";
                    }
                }
            });
        }

        function applyPodiumData(podium) {
            if (!podium) return;

            const winnerName = podium.winner ?? "";
            const secondName = podium.second_place ?? "";
            const thirdName = podium.third_place ?? "";

            const winnerEl = document.getElementById("victoryWinnerName");
            const firstEl = document.getElementById("victoryFirstName");
            const secondEl = document.getElementById("victorySecondName");
            const thirdEl = document.getElementById("victoryThirdName");

            if (winnerEl) winnerEl.textContent = winnerName;
            if (firstEl) firstEl.textContent = winnerName;
            if (secondEl) secondEl.textContent = secondName;
            if (thirdEl) thirdEl.textContent = thirdName;
        }

        function showVictoryOverlay() {

            const overlay = document.getElementById("victoryOverlay");
            if (!overlay) return;

            setTimeout(() => {
                overlay.classList.add("show");
                startConfetti();
            }, 800);

            // 👉 Klick zum Schließen
            overlay.addEventListener("click", () => {
                overlay.classList.remove("show");
            });
        }

        function startConfetti() {

            const duration = 7000
            const animationEnd = Date.now() + duration

            const defaults = {
                startVelocity: 30,
                spread: 360,
                ticks: 60,
                zIndex: 10000
            }

            function randomInRange(min, max) {
                return Math.random() * (max - min) + min
            }

            const interval = setInterval(function() {

                const timeLeft = animationEnd - Date.now()

                if (timeLeft <= 0) {
                    return clearInterval(interval)
                }

                const particleCount = 50 * (timeLeft / duration)

                confetti(Object.assign({}, defaults, {
                    particleCount,
                    origin: {
                        x: randomInRange(0.1, 0.3),
                        y: Math.random() - 0.2
                    }
                }))

                confetti(Object.assign({}, defaults, {
                    particleCount,
                    origin: {
                        x: randomInRange(0.7, 0.9),
                        y: Math.random() - 0.2
                    }
                }))

            }, 250)

        }
    </script>
@endpush
@push('styles')
    <style>
        .updated {
            animation: flash 0.4s;
        }

        @keyframes flash {
            from {
                background: rgba(255, 255, 0, 0.3);
            }

            to {
                background: transparent;
            }
        }

        .move {
            animation: moveRow 0.3s ease;
        }

        @keyframes moveRow {
            from {
                transform: translateY(-5px);
                background: rgba(255, 255, 0, 0.2);
            }

            to {
                transform: translateY(0);
                background: transparent;
            }
        }

        .live {
            color: #facc15;
            font-weight: bold;
        }

        .victory-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.92);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.8s;
            z-index: 9999;
        }

        .victory-overlay.show {
            opacity: 1;
            pointer-events: all;
        }

        .victory-overlay.show .place {
            animation: riseUp 1.2s ease forwards;
        }

        .second {
            animation-delay: 0.2s;
        }

        .first {
            animation-delay: 0.5s;
        }

        .third {
            animation-delay: 0.8s;
        }

        @keyframes riseUp {
            to {
                transform: translateY(0);
            }
        }

        .victory-content {
            text-align: center;
            color: white;
        }

        .victory-title {
            font-size: 50px;
            color: #fbbf24;
            margin-bottom: 30px;
        }

        .winner-name {
            font-size: 70px;
            font-weight: bold;
            margin-bottom: 60px;
            animation: pulseWinner 2s infinite;
        }

        @keyframes pulseWinner {
            0% {
                transform: scale(1)
            }

            50% {
                transform: scale(1.05)
            }

            100% {
                transform: scale(1)
            }
        }

        .podium {
            display: flex;
            align-items: flex-end;
            justify-content: center;
            gap: 60px;
        }

        .place {
            text-align: center;
            transform: translateY(400px);
        }

        .place .name {
            font-size: 22px;
            margin-bottom: 10px;
        }

        .step {
            width: 140px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            border-radius: 12px 12px 0 0;
        }

        /* 🥇 */
        .first .step {
            height: 220px;
            background: linear-gradient(180deg, #ffd700, #b8860b);
            box-shadow: 0 0 40px rgba(255, 215, 0, 0.8);
        }

        /* 🥈 */
        .second .step {
            height: 170px;
            background: linear-gradient(180deg, #d1d5db, #6b7280);
        }

        /* 🥉 */
        .third .step {
            height: 140px;
            background: linear-gradient(180deg, #cd7f32, #7c2d12);
        }
    </style>
@endpush
