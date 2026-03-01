export function initKnockout() {

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

    document.addEventListener('click', async function (e) {

        if (!e.target.classList.contains('save-btn')) return;

        const form = e.target.closest('.score-form');

        const success = await submitScore(form);

        if (success) location.reload();
    });

    document.addEventListener('keydown', async function (e) {

        if (e.key !== 'Enter') return;

        const input = e.target;
        if (!input.classList.contains('score-input')) return;

        e.preventDefault();

        const form = input.closest('.score-form');

        const success = await submitScore(form);

        if (success) location.reload();
    });
}