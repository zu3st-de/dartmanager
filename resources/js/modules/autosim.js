// resources/js/modules/autosim.js

function getSpeed() {
    const params = new URLSearchParams(window.location.search);
    return parseInt(params.get('speed')) || 1000;
}

function simulateGroup() {

    const forms = document.querySelectorAll('.simulate-group-form');

    for (let form of forms) {

        const inputs = form.querySelectorAll('input[type="number"]');
        if (inputs.length < 2) continue;

        if (inputs[0].value || inputs[1].value) continue;

        const bestOf = parseInt(form.dataset.bestof || 3);
        const needed = Math.ceil(bestOf / 2);

        const winnerIsFirst = Math.random() < 0.5;

        if (bestOf === 1) {

            inputs[0].value = winnerIsFirst ? 1 : 0;
            inputs[1].value = winnerIsFirst ? 0 : 1;

            const restField = form.querySelector('input[name="winning_rest"]');
            if (restField) {
                restField.value = Math.floor(Math.random() * 171);
            }

        } else {

            if (winnerIsFirst) {
                inputs[0].value = needed;
                inputs[1].value = Math.floor(Math.random() * needed);
            } else {
                inputs[0].value = Math.floor(Math.random() * needed);
                inputs[1].value = needed;
            }

        }

        form.submit();
        return true;
    }

    return false;
}

function simulateKoRound() {

    const forms = document.querySelectorAll('.simulate-ko-form');

    const openForms = Array.from(forms).filter(form => {

        const inputs = form.querySelectorAll('input[type="number"]');
        if (inputs.length < 2) return false;
        if (inputs[0].value || inputs[1].value) return false;

        return true;
    });

    if (!openForms.length) return false;

    const rounds = openForms.map(f => parseInt(f.dataset.round));
    const currentRound = Math.min(...rounds);

    const roundForms = openForms.filter(f =>
        parseInt(f.dataset.round) === currentRound
    );

    const form = roundForms[0];

    const inputs = form.querySelectorAll('input[type="number"]');
    const bestOf = parseInt(form.dataset.bestof || 3);
    const needed = Math.ceil(bestOf / 2);

    const winnerIsFirst = Math.random() < 0.5;

    if (winnerIsFirst) {
        inputs[0].value = needed;
        inputs[1].value = Math.floor(Math.random() * needed);
    } else {
        inputs[0].value = Math.floor(Math.random() * needed);
        inputs[1].value = needed;
    }

    form.submit();
    return true;
}

export function initAutoSim() {

    const params = new URLSearchParams(window.location.search);
    const mode = params.get('autosim');

    if (!mode) return;

    const speed = getSpeed();

    setTimeout(() => {

        let simulated = false;

        if (mode === 'groups') {
            simulated = simulateGroup();
        }

        if (mode === 'ko') {
            simulated = simulateKoRound();
        }

        if (!simulated) {
            console.log("AutoSim beendet.");
        }

    }, speed);
}