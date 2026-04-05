/*
|--------------------------------------------------------------------------
| AUTO SIMULATION MODULE (FINAL CLEAN VERSION)
|--------------------------------------------------------------------------
|
| Prinzip:
| - Steuerung erfolgt über Tournament Status (window.tournamentStatus)
| - group → simulateGroup()
| - ko    → simulateKoRound()
|
| WICHTIG:
| - Es wird ausschließlich mit `.save-btn` gearbeitet
| - Kein Fallback, kein Submit-Hack
|
| BESONDERHEIT:
| - Wenn in der Gruppenphase kein Save-Button mehr gefunden wird:
|   → Gruppenphase fertig
|   → Reload (damit KO-Button sichtbar wird)
|   → Autosim wird gestoppt
|
*/


/*
|--------------------------------------------------------------------------
| GLOBAL STATE
|--------------------------------------------------------------------------
*/
let autosimRunning = false;
let autosimTimeout = null;
let autosimSpeed = 800;

let UI = null;


/*
|--------------------------------------------------------------------------
| UI UPDATE
|--------------------------------------------------------------------------
*/
function updateUI() {
    if (!UI) return;

    UI.startBtn.disabled = autosimRunning;
    UI.stopBtn.disabled = !autosimRunning;

    UI.statusBox.innerText = autosimRunning
        ? "Status: Running"
        : "Status: Idle";
}


/*
|--------------------------------------------------------------------------
| START
|--------------------------------------------------------------------------
*/
function startAutoSim() {
    if (autosimRunning) return;

    autosimRunning = true;

    console.log("AutoSim gestartet");

    localStorage.setItem('autosim_running', '1');

    updateUI();
    runAutoSim();
}


/*
|--------------------------------------------------------------------------
| STOP
|--------------------------------------------------------------------------
*/
function stopAutoSim() {
    autosimRunning = false;

    if (autosimTimeout) {
        clearTimeout(autosimTimeout);
        autosimTimeout = null;
    }

    localStorage.removeItem('autosim_running');

    console.log("AutoSim gestoppt");

    updateUI();
}


/*
|--------------------------------------------------------------------------
| MAIN LOOP
|--------------------------------------------------------------------------
|
| Ablauf:
| 1. Status prüfen
| 2. passende Simulation ausführen
| 3. wenn nichts mehr zu simulieren → abhängig von Phase reagieren
|
*/
function runAutoSim() {

    if (!autosimRunning) return;

    const status = window.tournamentStatus;

    /*
    |--------------------------------------------------------------------------
    | GRUPPENPHASE
    |--------------------------------------------------------------------------
    */
    if (status === 'group_running') {

        const simulated = simulateGroup();

        // ❗ Keine Buttons mehr → Gruppen fertig
        if (!simulated) {

            console.log("Gruppen fertig → reload & stop");

            stopAutoSim();

            // wichtig für KO-Button / neue UI
            setTimeout(() => {
                location.reload();
            }, 500);

            return;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | KO-PHASE
    |--------------------------------------------------------------------------
    */
    else if (status === 'ko_running') {

        const simulated = simulateKoRound();

        if (!simulated) {
            console.log("KO fertig");
            stopAutoSim();
            //Siegerehrung / neue UI
            setTimeout(() => {
                location.reload();
            }, 500);
            return;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | UNBEKANNTER STATUS
    |--------------------------------------------------------------------------
    */
    else {
        console.log("Unbekannter Status → stop");
        console.log(status);
        stopAutoSim();
        return;
    }

    /*
    |--------------------------------------------------------------------------
    | NÄCHSTER SCHRITT
    |--------------------------------------------------------------------------
    */
    autosimTimeout = setTimeout(runAutoSim, autosimSpeed);
}


/*
|--------------------------------------------------------------------------
| GROUP SIMULATION
|--------------------------------------------------------------------------
|
| - sucht offenes Spiel
| - trägt Ergebnis ein
| - klickt `.save-btn`
|
| RETURN:
| true  → Spiel simuliert
| false → keine Spiele mehr → Gruppenphase fertig
|
*/
function simulateGroup() {

    const forms = document.querySelectorAll('.simulate-group-form');
    if (!forms.length) return false;

    // Gruppen sammeln
    const groups = {};

    forms.forEach(form => {

        const groupId = form.dataset.group;

        if (!groups[groupId]) {
            groups[groupId] = [];
        }

        const inputs = form.querySelectorAll('input[type="number"]');

        // nur offene Spiele
        if (inputs[0].value === '' && inputs[1].value === '') {
            groups[groupId].push(form);
        }

    });

    const groupIds = Object.keys(groups).filter(id => groups[id].length);

    if (!groupIds.length) return false;

    // Round robin Gruppe
    let groupIndex = parseInt(localStorage.getItem('autosim_group_index') || 0);

    if (groupIndex >= groupIds.length) groupIndex = 0;

    const groupId = groupIds[groupIndex];
    const form = groups[groupId][0]; // immer erstes Spiel

    const inputs = form.querySelectorAll('input[type="number"]');
    const btn = document.querySelector(`.save-btn[form="${form.id}"]`);

    const bestOf = parseInt(form.dataset.bestof || 3);
    const needed = Math.ceil(bestOf / 2);

    if (Math.random() < 0.5) {
        inputs[0].value = needed;
        inputs[1].value = Math.floor(Math.random() * needed);
    } else {
        inputs[0].value = Math.floor(Math.random() * needed);
        inputs[1].value = needed;
    }

    localStorage.setItem('autosim_group_index', groupIndex + 1);

    btn.click();

    return true;
}


/*
|--------------------------------------------------------------------------
| KO SIMULATION
|--------------------------------------------------------------------------
|
| - identisch zur Gruppenlogik
| - arbeitet rundenweise automatisch
|
*/
function simulateKoRound() {

    const forms = document.querySelectorAll('.simulate-ko-form');
    if (!forms.length) return false;

    for (const form of forms) {

        const btn = form.querySelector('.save-btn');
        if (!btn) continue;

        const inputs = form.querySelectorAll('input[type="number"]');
        if (inputs.length < 2) continue;

        if (inputs[0].value !== '' || inputs[1].value !== '') continue;

        const bestOf = parseInt(form.dataset.bestof || 3);
        const needed = Math.ceil(bestOf / 2);

        if (Math.random() < 0.5) {
            inputs[0].value = needed;
            inputs[1].value = Math.floor(Math.random() * needed);
        } else {
            inputs[0].value = Math.floor(Math.random() * needed);
            inputs[1].value = needed;
        }

        btn.click();

        return true;
    }

    return false;
}


/*
|--------------------------------------------------------------------------
| INIT
|--------------------------------------------------------------------------
*/
document.addEventListener('DOMContentLoaded', () => {

    UI = {
        startBtn: document.getElementById('autosimStart'),
        stopBtn: document.getElementById('autosimStop'),
        speedSlider: document.getElementById('autosimSpeed'),
        speedLabel: document.getElementById('autosimSpeedLabel'),
        statusBox: document.getElementById('autosimStatus')
    };

    if (!UI.startBtn) return;

    UI.startBtn.addEventListener('click', startAutoSim);
    UI.stopBtn.addEventListener('click', stopAutoSim);

    UI.speedSlider?.addEventListener('input', () => {
        autosimSpeed = parseInt(UI.speedSlider.value);
        UI.speedLabel.innerText = autosimSpeed + 'ms';
    });

    updateUI();

    console.log("AutoSim bereit");

});


/*
|--------------------------------------------------------------------------
| EXPORT (Vite)
|--------------------------------------------------------------------------
*/
export function initAutoSim() { }