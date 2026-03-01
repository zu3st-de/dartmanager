export function initGroups() {

    async function submitScore(form) {

        const inputs = form.querySelectorAll('.score-input');

        if (!inputs[0].value || !inputs[1].value) return false;

        const formData = new FormData();

        formData.append('_token',
            form.querySelector('.csrf-token').value);

        formData.append('player1_score', inputs[0].value);
        formData.append('player2_score', inputs[1].value);

        const response = await fetch(form.dataset.url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        });

        return response.ok;
    }

    // Einzelnes Spiel speichern (Button)
    document.addEventListener('click', async function (e) {

        if (!e.target.classList.contains('group-save-btn')) return;

        const form = e.target.closest('.score-form');

        const success = await submitScore(form);

        if (success) location.reload();
    });

    // Enter-Handling
    document.addEventListener('keydown', async function (e) {

        if (e.key !== 'Enter') return;

        const input = e.target;

        if (!input.classList.contains('group-score-input')) return;

        e.preventDefault();

        const form = input.closest('.score-form');

        const success = await submitScore(form);

        if (success) location.reload();
    });

    // Alle Spiele speichern
    const saveAllBtn = document.getElementById('save-all-groups-btn');

    if (saveAllBtn) {

        saveAllBtn.addEventListener('click', async function () {

            const forms = document.querySelectorAll('.score-form');

            for (const form of forms) {
                await submitScore(form);
            }

            location.reload();
        });

    }
}