<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta http-equiv="refresh" content="10">

    <style>
        .victory-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.92);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            pointer-events: none;
            transition: opacity 1.5s;
            z-index: 9999;
        }

        .victory-overlay.show {
            opacity: 1;
        }

        .victory-content {
            text-align: center;
            color: white;
        }

        .victory-title {
            font-size: 60px;
            color: #fbbf24;
            margin-bottom: 40px;
        }

        .winner-name {
            font-size: 90px;
            font-weight: bold;
            margin-bottom: 80px;
            animation: pulseWinner 2s infinite;
        }

        @keyframes pulseWinner {
            0% {
                transform: scale(1)
            }

            50% {
                transform: scale(1.06)
            }

            100% {
                transform: scale(1)
            }
        }

        .podium {
            display: flex;
            align-items: flex-end;
            justify-content: center;
            gap: 90px;
            margin-top: 60px;
        }

        .place {
            text-align: center;
            color: white;
            transform: translateY(500px);
        }

        .victory-overlay.show .place {
            animation: riseUp 1.6s ease forwards;
        }

        .second {
            animation-delay: 0.4s;
        }

        .first {
            animation-delay: 0.8s;
        }

        .third {
            animation-delay: 1.2s;
        }

        @keyframes riseUp {
            to {
                transform: translateY(0);
            }
        }


        .place .name {
            font-size: 32px;
            margin-bottom: 15px;
        }

        .step {
            width: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 60px;
            border-radius: 12px 12px 0 0;
        }

        .first .step {
            height: 320px;
            background: linear-gradient(180deg, #ffd700, #b8860b);
            box-shadow: 0 0 60px rgba(255, 215, 0, 0.9);
        }

        .second .step {
            height: 240px;
            background: linear-gradient(180deg, #d1d5db, #6b7280);
        }

        .third .step {
            height: 200px;
            background: linear-gradient(180deg, #cd7f32, #7c2d12);
        }

        .place div:first-child {
            font-size: 28px;
            margin-bottom: 10px;
        }

        #confettiCanvas {
            position: absolute;
            inset: 0;
            pointer-events: none;
        }

        .winner-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.85);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            pointer-events: none;
            transition: opacity 1.5s;
        }

        .winner-overlay.show {
            opacity: 1;
        }

        .victory {
            text-align: center;
        }

        .title {
            font-size: 60px;
            color: #fbbf24;
            margin-bottom: 20px;
        }

        .winner-name {
            font-size: 90px;
            font-weight: bold;
            margin-bottom: 80px;
            animation: winnerPulse 2.5s infinite;
        }

        @keyframes winnerPulse {
            0% {
                transform: scale(1)
            }

            50% {
                transform: scale(1.08)
            }

            100% {
                transform: scale(1)
            }
        }

        body {
            margin: 0;
            background: #111827;
            font-family: Inter, Arial, sans-serif;
        }

        svg {
            width: 100vw;
            height: 100vh;
            display: block;
        }

        .match-box {
            fill: #1f2937;
            rx: 10;
        }

        .match-text {
            fill: #f3f4f6;
            font-size: 18px;
        }

        .line {
            stroke: #6b7280;
            stroke-width: 2;
            fill: none;
        }

        .gold {
            fill: #fbbf24;
            font-weight: bold;
        }

        .silver {
            fill: #e5e7eb;
            font-weight: bold;
        }

        .bronze {
            fill: #d97706;
            font-weight: bold;
        }

        .winner {
            fill: #22c55e;
            font-weight: bold;
        }

        .subtitle {
            fill: #9ca3af;
            font-size: 20px;
        }
    </style>
</head>

<body>

    <div id="victoryOverlay" class="victory-overlay">

        <canvas id="confettiCanvas"></canvas>

        <div class="victory-content">

            <div class="victory-title">
                🏆 Turniersieger 🏆
            </div>

            <div id="winnerName" class="winner-name"></div>

            <div class="podium">

                <div class="place second">
                    <div class="name" id="secondName"></div>
                    <div class="step">
                        🥈
                    </div>
                </div>

                <div class="place first">
                    <div class="name" id="firstName"></div>
                    <div class="step">
                        🏆
                    </div>
                </div>

                <div class="place third">
                    <div class="name" id="thirdName"></div>
                    <div class="step">
                        🥉
                    </div>
                </div>

            </div>

        </div>

    </div>

    <svg id="bracket"
        viewBox="0 0 1920 1080"
        preserveAspectRatio="xMidYMid meet">

        <text id="title"
            x="960"
            y="80"
            text-anchor="middle"
            fill="#f9fafb"
            font-size="48"
            font-weight="bold">
            {{ $tournament->name }}
        </text>

        <g id="bracketLayer"></g>

    </svg>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    <script>
        const rounds = @json($rounds ?? []) || {};
        const lastRound = Math.max(...Object.keys(rounds).map(Number))

        const layer = document.getElementById("bracketLayer")

        const baseMatchWidth = 260
        const baseMatchHeight = 70

        const roundSpacing = 170
        const baseSpacing = 300

        const positions = {}

        function createMatch(match, x, y, round, index) {

            const scale = Math.pow(1.2, round - 1)

            const width = baseMatchWidth * scale
            const height = baseMatchHeight * scale

            const g = document.createElementNS("http://www.w3.org/2000/svg", "g")

            const rect = document.createElementNS("http://www.w3.org/2000/svg", "rect")
            rect.setAttribute("x", x)
            rect.setAttribute("y", y)
            rect.setAttribute("width", width)
            rect.setAttribute("height", height)
            rect.setAttribute("class", "match-box")

            g.appendChild(rect)

            let name1 = match.player1?.name ?? "TBD"
            let name2 = match.player2?.name ?? "TBD"

            let class1 = "match-text"
            let class2 = "match-text"

            /* Finale */
            if (round === lastRound && index === 0 && match.winner_id) {

                if (match.winner_id === match.player1_id) {

                    class1 += " gold"
                    class2 += " silver"

                    name1 = "🥇 " + name1
                    name2 = "🥈 " + name2

                } else {

                    class2 += " gold"
                    class1 += " silver"

                    name2 = "🥇 " + name2
                    name1 = "🥈 " + name1

                }

            }

            /* Platz 3 */
            else if (round === lastRound && index === 1 && match.winner_id) {

                if (match.winner_id === match.player1_id) {

                    class1 += " bronze"
                    name1 = "🥉 " + name1

                } else {

                    class2 += " bronze"
                    name2 = "🥉 " + name2

                }

            } else {

                if (match.winner_id === match.player1_id) class1 += " winner"
                if (match.winner_id === match.player2_id) class2 += " winner"

            }

            /* Name 1 */

            const p1 = document.createElementNS("http://www.w3.org/2000/svg", "text")
            p1.setAttribute("x", x + 14)
            p1.setAttribute("y", y + height * 0.4)
            p1.setAttribute("class", class1)
            p1.textContent = name1

            g.appendChild(p1)

            /* Score 1 */

            const s1 = document.createElementNS("http://www.w3.org/2000/svg", "text")
            s1.setAttribute("x", x + width - 14)
            s1.setAttribute("y", y + height * 0.4)
            s1.setAttribute("text-anchor", "end")
            s1.setAttribute("class", "match-text")
            s1.textContent = match.player1_score ?? ""

            g.appendChild(s1)

            /* Name 2 */

            const p2 = document.createElementNS("http://www.w3.org/2000/svg", "text")
            p2.setAttribute("x", x + 14)
            p2.setAttribute("y", y + height * 0.75)
            p2.setAttribute("class", class2)
            p2.textContent = name2

            g.appendChild(p2)

            /* Score 2 */

            const s2 = document.createElementNS("http://www.w3.org/2000/svg", "text")
            s2.setAttribute("x", x + width - 14)
            s2.setAttribute("y", y + height * 0.75)
            s2.setAttribute("text-anchor", "end")
            s2.setAttribute("class", "match-text")
            s2.textContent = match.player2_score ?? ""

            g.appendChild(s2)

            /* Platz 3 Überschrift */

            if (round === lastRound && index === 1) {

                const label = document.createElementNS("http://www.w3.org/2000/svg", "text")

                label.setAttribute("x", x + width / 2)
                label.setAttribute("y", y - 20)
                label.setAttribute("text-anchor", "middle")
                label.setAttribute("class", "subtitle")

                label.textContent = "Spiel um Platz 3"

                g.appendChild(label)

            }

            layer.appendChild(g)

            return {
                x,
                y,
                width,
                height
            }

        }

        function drawLine(x1, y1, x2, y2) {

            const midY = (y1 + y2) / 2

            const path = document.createElementNS("http://www.w3.org/2000/svg", "path")

            path.setAttribute(
                "d",
                `M ${x1} ${y1}
         V ${midY}
         H ${x2}
         V ${y2}`
            )

            path.setAttribute("class", "line")

            layer.appendChild(path)

        }

        const roundKeys = Object.keys(rounds).map(Number)

        const firstRoundCount = rounds[1]?.length || 0

        // reale Breite des ersten Rundenblocks
        const totalWidth = (firstRoundCount - 1) * baseSpacing + baseMatchWidth

        // Mitte des Screens (1920)
        const centerOffset = (1920 - totalWidth) / 2

        roundKeys.forEach(roundIndex => {

            const round = rounds[roundIndex]

            const spacing = baseSpacing * Math.pow(2, roundIndex - 1)
            const offset = spacing / 2

            round.forEach((match, i) => {

                let x = centerOffset + offset + i * spacing
                let y = 120 + (roundIndex - 1) * roundSpacing

                if (roundIndex === lastRound && i === 1) {

                    const finalMatch = rounds[lastRound][0]
                    const finalPos = positions[finalMatch.id]

                    x = finalPos.x + 420
                    y = finalPos.y + 140

                }

                positions[match.id] = createMatch(match, x, y, roundIndex, i)

            })

        })

        roundKeys.forEach(roundIndex => {

            const round = rounds[roundIndex]
            const nextRound = rounds[roundIndex + 1]

            if (!nextRound) return

            for (let i = 0; i < round.length; i += 2) {

                const m1 = round[i]
                const m2 = round[i + 1]
                const next = nextRound[i / 2]

                if (!m1 || !m2 || !next) continue

                const p1 = positions[m1.id]
                const p2 = positions[m2.id]
                const pn = positions[next.id]

                drawLine(p1.x + p1.width / 2, p1.y + p1.height, pn.x + pn.width / 2, pn.y)
                drawLine(p2.x + p2.width / 2, p2.y + p2.height, pn.x + pn.width / 2, pn.y)

            }

        })

        function scaleBracket() {

            const bbox = layer.getBBox()

            const scale = Math.min(
                1920 / bbox.width,
                1080 / bbox.height
            ) * 0.92

            const offsetX = 960 - (bbox.x + bbox.width / 2) * scale
            const offsetY = 540 - (bbox.y + bbox.height / 2) * scale

            layer.setAttribute(
                "transform",
                `translate(${offsetX},${offsetY}) scale(${scale})`
            )

        }

        scaleBracket()

        function showVictory() {

            const finalRound = rounds[lastRound]

            if (!finalRound) return

            const finalMatch = finalRound[0]
            const thirdMatch = finalRound[1]

            if (!finalMatch?.winner_id) return

            let first = ""
            let second = ""
            let third = ""

            if (finalMatch.winner_id === finalMatch.player1_id) {
                first = finalMatch.player1?.name
                second = finalMatch.player2?.name
            } else {
                first = finalMatch.player2?.name
                second = finalMatch.player1?.name
            }

            if (thirdMatch?.winner_id) {
                if (thirdMatch.winner_id === thirdMatch.player1_id) {
                    third = thirdMatch.player1?.name
                } else {
                    third = thirdMatch.player2?.name
                }
            }

            document.getElementById("winnerName").textContent = first
            document.getElementById("firstName").textContent = first
            document.getElementById("secondName").textContent = second
            document.getElementById("thirdName").textContent = third

            const overlay = document.getElementById("victoryOverlay")

            setTimeout(() => {
                overlay.classList.add("show")
                startConfetti()
            }, 1500)

        }

        function startConfetti() {

            const duration = 8000
            const end = Date.now() + duration

            (function frame() {

                confetti({
                    particleCount: 5,
                    spread: 70,
                    origin: {
                        y: 0.6
                    }
                })

                if (Date.now() < end) {
                    requestAnimationFrame(frame)
                }

            })()

        }

        showVictory()

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
        showVictory()
    </script>

</body>

</html>