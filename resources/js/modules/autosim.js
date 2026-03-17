/*
|--------------------------------------------------------------------------
| ⚙️ AUTO SIMULATION MODULE (FINAL STABLE)
|--------------------------------------------------------------------------
|
| Unterstützt:
| - Gruppenphase Simulation
| - KO-Phase Simulation
| - AJAX kompatibel
| - läuft rekursiv bis alles fertig ist
|
| Features:
| - nutzt echten DOM State (keine falschen Flags)
| - robust gegen Reloads
| - kein doppeltes Ausführen
|
*/

/*
|--------------------------------------------------------------------------
| ⏱ Geschwindigkeit aus URL lesen
|--------------------------------------------------------------------------
|
| Beispiel:
| ?autosim=ko&speed=500
|
*/
function getSpeed() {
    const params = new URLSearchParams(window.location.search);
    return parseInt(params.get('speed')) || 800;
}


/*
|--------------------------------------------------------------------------
| 🟢 GRUPPENPHASE SIMULATION
|--------------------------------------------------------------------------
*/
function simulateGroup() {

    const groups = document.querySelectorAll('[data-group]');
    if (!groups.length) return false;

    let currentIndex = parseInt(localStorage.getItem('autosimGroupIndex') || 0);

    for (let i = 0; i < groups.length; i++) {

        const group = groups[(currentIndex + i) % groups.length];
        const forms = group.querySelectorAll('.simulate-group-form');

        for (const form of forms) {

            const inputs = form.querySelectorAll('input[type="number"]');
            if (inputs.length < 2) continue;

            const p1 = inputs[0];
            const p2 = inputs[1];

            // bereits gespielt → skip
            if (p1.value !== '' || p2.value !== '') continue;

            const bestOf = parseInt(form.dataset.bestof || 3);
            const needed = Math.ceil(bestOf / 2);
            const winnerIsFirst = Math.random() < 0.5;

            /*
            |--------------------------------------------------------------------------
            | Best-of 1 Sonderfall
            |--------------------------------------------------------------------------
            */
            if (bestOf === 1) {

                p1.value = winnerIsFirst ? 1 : 0;
                p2.value = winnerIsFirst ? 0 : 1;

                const restField = form.querySelector('input[name="winning_rest"]');
                if (restField) {
                    restField.value = Math.floor(Math.random() * 171);
                }

            } else {

                if (winnerIsFirst) {
                    p1.value = needed;
                    p2.value = Math.floor(Math.random() * needed);
                } else {
                    p1.value = Math.floor(Math.random() * needed);
                    p2.value = needed;
                }

            }

            // Fortschritt merken
            localStorage.setItem(
                'autosimGroupIndex',
                (currentIndex + i + 1) % groups.length
            );

            // AJAX Submit triggern
            form.dispatchEvent(new Event('submit', { bubbles: true }));

            return true;
        }
    }

    return false;
}


/*
|--------------------------------------------------------------------------
| 🔴 KO-PHASE SIMULATION
|--------------------------------------------------------------------------
|
| WICHTIG:
| - nimmt IMMER die niedrigste offene Runde
| - simuliert exakt 1 Spiel pro Durchlauf
| - läuft rekursiv weiter
|
*/
function simulateKoRound() {

    const forms = document.querySelectorAll('.simulate-ko-form');
    if (!forms.length) return false;

    /*
    |--------------------------------------------------------------------------
    | Offene Spiele filtern
    |--------------------------------------------------------------------------
    */
    const openForms = Array.from(forms).filter(form => {

        if (!form) return false;

        const inputs = form.querySelectorAll('input[type="number"]');
        if (!inputs || inputs.length < 2) return false;

        // bereits gespielt → skip
        if (inputs[0].value || inputs[1].value) return false;

        return true;
    });

    if (!openForms.length) return false;

    /*
    |--------------------------------------------------------------------------
    | Früheste Runde bestimmen
    |--------------------------------------------------------------------------
    */
    const rounds = openForms.map(f => parseInt(f.dataset.round));
    const currentRound = Math.min(...rounds);

    /*
    |--------------------------------------------------------------------------
    | Nur Spiele dieser Runde
    |--------------------------------------------------------------------------
    */
    const roundForms = openForms.filter(f =>
        parseInt(f.dataset.round) === currentRound
    );

    if (!roundForms.length) return false;

    const form = roundForms[0];

    const inputs = form.querySelectorAll('input[type="number"]');
    if (!inputs || inputs.length < 2) return false;

    /*
    |--------------------------------------------------------------------------
    | Best-of Logik
    |--------------------------------------------------------------------------
    */
    const bestOf = parseInt(form.dataset.bestof || 3);
    const needed = Math.ceil(bestOf / 2);

    const winnerIsFirst = Math.random() < 0.5;

    /*
    |--------------------------------------------------------------------------
    | Ergebnis setzen
    |--------------------------------------------------------------------------
    */
    if (winnerIsFirst) {
        inputs[0].value = needed;
        inputs[1].value = Math.floor(Math.random() * needed);
    } else {
        inputs[0].value = Math.floor(Math.random() * needed);
        inputs[1].value = needed;
    }

    /*
    |--------------------------------------------------------------------------
    | AJAX Submit (leicht verzögert für DOM-Stabilität)
    |--------------------------------------------------------------------------
    */
    setTimeout(() => {
        form.dispatchEvent(
            new Event('submit', { bubbles: true, cancelable: true })
        );
    }, 50);

    return true;
}


/*
|--------------------------------------------------------------------------
| 🔁 LOOP STEUERUNG (REKURSIV)
|--------------------------------------------------------------------------
|
| Führt Simulation solange aus bis nichts mehr zu tun ist
|
*/
function runAutoSim(mode) {

    let simulated = false;

    if (mode === 'groups') {
        simulated = simulateGroup();
    }

    if (mode === 'ko') {
        simulated = simulateKoRound();
    }

    /*
    |--------------------------------------------------------------------------
    | Wenn nichts mehr simuliert wurde → STOP
    |--------------------------------------------------------------------------
    */
    if (!simulated) {
        console.log("AutoSim beendet.");
        return;
    }

    /*
    |--------------------------------------------------------------------------
    | Nächster Schritt
    |--------------------------------------------------------------------------
    */
    setTimeout(() => {
        runAutoSim(mode);
    }, getSpeed());
}


/*
|--------------------------------------------------------------------------
| 🚀 INIT
|--------------------------------------------------------------------------
|
| Startet Simulation abhängig von URL Param
|
| Beispiele:
| ?autosim=groups
| ?autosim=ko
| ?autosim=ko&speed=300
|
*/
export function initAutoSim() {

    const params = new URLSearchParams(window.location.search);
    const mode = params.get('autosim');

    if (!mode) return;

    console.log("AutoSim gestartet:", mode);

    // kleine Startverzögerung für DOM/AJAX
    setTimeout(() => {
        runAutoSim(mode);
    }, getSpeed());
}