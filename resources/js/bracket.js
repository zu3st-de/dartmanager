document.addEventListener("DOMContentLoaded", () => {

    const svg = document.getElementById("bracket");
    const matches = window.matches ?? [];

    if (!svg || matches.length === 0) return;

    const MATCH_W = 220;
    const MATCH_H = 36;

    const CENTER_X = 900;
    const START_Y = 120;
    const X_STEP = 260;
    const Y_STEP = 70;

    function el(type) {
        return document.createElementNS("http://www.w3.org/2000/svg", type);
    }

    function drawMatch(x, y, text) {

        const r = el("rect");
        r.setAttribute("x", x);
        r.setAttribute("y", y);
        r.setAttribute("width", MATCH_W);
        r.setAttribute("height", MATCH_H);
        r.setAttribute("rx", "6");
        r.setAttribute("fill", "#1f2937");

        svg.appendChild(r);

        const t = el("text");
        t.setAttribute("x", x + 10);
        t.setAttribute("y", y + 22);
        t.setAttribute("fill", "white");

        t.textContent = text;

        svg.appendChild(t);
    }

    /*
    Matches sortieren
    */

    matches.sort((a, b) => {
        if (a.round === b.round) {
            return a.position - b.position;
        }
        return a.round - b.round;
    });

    /*
    Matches nach Runde gruppieren
    */

    const rounds = {};

    matches.forEach(m => {
        if (!rounds[m.round]) rounds[m.round] = [];
        rounds[m.round].push(m);
    });

    const roundNumbers = Object.keys(rounds)
        .map(Number)
        .sort((a, b) => a - b);

    const finalRound = Math.max(...roundNumbers);

    /*
    Finale
    */

    const finalMatch = rounds[finalRound][0];

    const fp1 = finalMatch.player1?.name ?? "TBD";
    const fp2 = finalMatch.player2?.name ?? "TBD";

    drawMatch(CENTER_X, 400, `${fp1} vs ${fp2}`);

    /*
    Halbfinale
    */

    const semi = rounds[finalRound - 1] ?? [];

    semi.forEach((m, i) => {

        const x = i === 0 ? CENTER_X - X_STEP : CENTER_X + X_STEP;
        const y = 300;

        const p1 = m.player1?.name ?? "TBD";
        const p2 = m.player2?.name ?? "TBD";

        drawMatch(x, y, `${p1} vs ${p2}`);

    });

    /*
    Viertelfinale
    */

    const quarter = rounds[finalRound - 2] ?? [];

    quarter.forEach((m, i) => {

        const side = i < 2 ? -1 : 1;

        const x = CENTER_X + side * (X_STEP * 2);
        const y = START_Y + (i % 2) * 200;

        const p1 = m.player1?.name ?? "TBD";
        const p2 = m.player2?.name ?? "TBD";

        drawMatch(x, y, `${p1} vs ${p2}`);

    });

    /*
    Achtelfinale
    */

    const eighth = rounds[1] ?? [];

    eighth.forEach((m, i) => {

        const side = i < 4 ? -1 : 1;

        const x = CENTER_X + side * (X_STEP * 3);
        const y = START_Y + (i % 4) * Y_STEP;

        const p1 = m.player1?.name ?? "TBD";
        const p2 = m.player2?.name ?? "TBD";

        drawMatch(x, y, `${p1} vs ${p2}`);

    });

});