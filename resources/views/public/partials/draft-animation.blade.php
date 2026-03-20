{{-- ============================================================
   🎰 SLOT DRAW (FINAL CLEAN VERSION)
============================================================ --}}

<div class="slot-wrapper">

    <div id="stage">

        <div id="activeMatch" class="arena">
            <div class="slot" id="slot1">
                <div class="slot-inner"></div>
            </div>
            <div class="vs">VS</div>
            <div class="slot" id="slot2">
                <div class="slot-inner"></div>
            </div>
        </div>

    </div>

</div>

@push('styles')
    <style>
        .slot-wrapper {
            padding-top: 40px;
            position: relative;
        }

        #stage {
            position: relative;
        }

        /* ===== ARENA ===== */
        .arena {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 40px;
            height: 140px;
            position: relative;
            z-index: 2;
        }

        /* ===== SLOT ===== */
        .slot {
            width: 260px;
            height: 60px;
            overflow: hidden;
            border-radius: 10px;
            border: 2px solid #374151;
            background: #111827;
            position: relative;

            transition: border 0.3s, box-shadow 0.3s;
        }

        .slot-inner {
            position: absolute;
            width: 100%;
        }

        .slot-item {
            height: 60px;
            line-height: 60px;
            text-align: center;
            font-weight: bold;
            color: #e5e7eb;
        }

        /* ===== VS ===== */
        .vs {
            font-size: 22px;
            color: #facc15;
        }

        /* ===== MATCH HIGHLIGHT ===== */
        .match-highlight .slot {
            border-color: #22c55e;
            box-shadow:
                0 0 10px rgba(34, 197, 94, 0.8),
                0 0 25px rgba(34, 197, 94, 0.6);
        }

        /* ===== SNAPSHOT ===== */
        .snapshot {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;

            display: flex;
            justify-content: center;
            gap: 40px;

            z-index: 999;

            transform: translateY(0) scale(1);
            opacity: 1;

            transition:
                transform 1.4s cubic-bezier(0.22, 1, 0.36, 1),
                opacity 0.6s ease 0.9s;
        }

        .snapshot.animate {
            transform: translateY(180px) scale(0.8);
            opacity: 0;
        }
    </style>
@endpush


@push('scripts')
    <script>
        /* ===== PLAYERS ===== */

        let players = @json($players->pluck('name'));

        const fallback = [
            "Luke Humphries", "Michael van Gerwen", "Gerwyn Price",
            "Nathan Aspinall", "Rob Cross", "Peter Wright",
            "Jonny Clayton", "Danny Noppert", "Dave Chisnall",
            "Dimitri Van den Bergh"
        ];

        function getPlayers() {
            return players.length ? players : fallback;
        }


        /* ===== SLOT SPIN ===== */

        async function spinSlot(slotEl, factor = 1) {

            const inner = slotEl.querySelector(".slot-inner");
            inner.innerHTML = "";

            const pool = getPlayers();

            for (let i = 0; i < 25; i++) {
                const div = document.createElement("div");
                div.className = "slot-item";
                div.innerText = pool[Math.floor(Math.random() * pool.length)];
                inner.appendChild(div);
            }

            let pos = 0;
            let speed = 28;
            let duration = 2200 * factor;
            let elapsed = 0;

            while (elapsed < duration) {
                pos -= speed;
                inner.style.transform = `translateY(${pos}px)`;

                await wait(50);

                elapsed += 50;
                speed *= 0.94;
            }

            const items = inner.children;
            const index = Math.floor(items.length / 2);

            inner.style.transform = `translateY(-${index * 60}px)`;

            return items[index].innerText;
        }


        /* ===== SNAPSHOT ===== */

        function createSnapshot() {

            const stage = document.getElementById("stage");
            const active = document.getElementById("activeMatch");

            const clone = active.cloneNode(true);
            clone.classList.add("snapshot");

            stage.appendChild(clone);

            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    clone.classList.add("animate");
                });
            });

            setTimeout(() => clone.remove(), 1600);
        }


        /* ===== MAIN LOOP ===== */

        async function runDraw() {

            const s1 = document.getElementById("slot1");
            const s2 = document.getElementById("slot2");
            const active = document.getElementById("activeMatch");

            while (true) {

                // 🎰 beide starten
                const spin1 = spinSlot(s1, 1);
                const spin2 = spinSlot(s2, 1.4);

                await spin1;
                await spin2;

                // 💡 Highlight AN
                active.classList.add("match-highlight");

                await wait(800);

                // 🔥 Snapshot inkl Highlight
                createSnapshot();

                await wait(200);

                // Highlight aus für nächste Runde
                active.classList.remove("match-highlight");

                await wait(200);
            }
        }


        /* ===== UTILS ===== */

        function wait(ms) {
            return new Promise(r => setTimeout(r, ms));
        }


        /* ===== START ===== */

        document.addEventListener("DOMContentLoaded", runDraw);
    </script>
@endpush
