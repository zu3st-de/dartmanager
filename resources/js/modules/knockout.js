let knockoutInitialized = false;
let reloadLock = false;

export function initKnockout() {

    if (knockoutInitialized) return;
    knockoutInitialized = true;


    async function post(form) {

        const response = await fetch(form.dataset.url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new FormData(form)
        });

        return response.json();
    }

    const reloadingGames = new Set();

    window.reloadKoGame = async function (gameId) {

        try {

            const el = document.querySelector(`[data-game-id="${gameId}"]`);
            if (!el) return;

            const container = el.closest('.absolute');

            const res = await fetch(`/games/${gameId}/html`);
            const html = await res.text();

            const temp = document.createElement('div');
            temp.innerHTML = html;

            const newEl = temp.firstElementChild;

            if (container && newEl) {
                container.innerHTML = '';
                container.appendChild(newEl);
            }

        } catch (err) {
            console.error('reloadKoGame error:', err);
        }
    };


    async function handleSubmit(form) {

        if (reloadLock) return;
        reloadLock = true;

        let response = null;   // 🔥 FIX

        try {

            if (form.classList.contains('score-form')) {

                const inputs = form.querySelectorAll('.score-input');

                if (inputs.length === 2) {

                    const val1 = inputs[0].value;
                    const val2 = inputs[1].value;

                    if (!val1 || !val2) {
                        reloadLock = false;
                        return;
                    }
                }
            }

            response = await post(form);

            // 🔥 FULL RELOAD zuerst prüfen
            if (response?.fullReload) {
                window.location.reload();
                return;
            }

            if (!response?.reload) return;

            // doppelte IDs entfernen
            const uniqueIds = [...new Set(response.reload)];

            for (const id of uniqueIds) {
                await window.reloadKoGame(id);
            }

            return response;

        } finally {
            reloadLock = false;
        }
    }

    window.submitKoForm = handleSubmit;


    document.addEventListener('click', async function (e) {

        const form = e.target.closest('.score-form, .reset-form');

        if (!form) return;

        e.preventDefault();

        await handleSubmit(form);
    });


    document.addEventListener('keydown', async function (e) {

        if (e.key !== 'Enter') return;

        const input = e.target;

        if (!input.classList.contains('score-input')) return;

        e.preventDefault();

        const form = input.closest('.score-form');

        if (!form) return;

        await handleSubmit(form);
    });


    document.addEventListener('submit', function (e) {

        if (
            e.target.classList.contains('score-form') ||
            e.target.classList.contains('reset-form')
        ) {
            e.preventDefault();
        }
    });

}
