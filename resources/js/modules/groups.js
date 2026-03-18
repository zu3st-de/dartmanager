export function initGroups() {

    /*
    |--------------------------------------------------------------------------
    | 🔧 SCORE SUBMIT (AJAX)
    |--------------------------------------------------------------------------
    */
    async function submitScore(form) {

        const inputs = form.querySelectorAll('.group-score-input');

        // 🔒 nur wenn beide Scores vorhanden
        if (!inputs[0].value || !inputs[1].value) return false;

        const formData = new FormData();

        // 🔐 CSRF Token
        formData.append(
            '_token',
            document.querySelector('meta[name="csrf-token"]').content
        );

        // 🔢 Scores
        formData.append('player1_score', inputs[0].value);
        formData.append('player2_score', inputs[1].value);

        // 🎯 optional: Rest (nur bei BO1 vorhanden)
        const restInput = form.querySelector('[name="winning_rest"]');
        if (restInput) {
            formData.append('winning_rest', restInput.value);
        }

        const response = await fetch(form.dataset.url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        });

        console.log('STATUS:', response.status);

        // ❌ Fehler sauber loggen
        if (!response.ok) {
            const errorText = await response.text();
            console.error('SERVER ERROR:', errorText);
            return false;
        }

        const data = await response.json();

        console.log('DATA:', data);

        return data;
    }


    /*
    |--------------------------------------------------------------------------
    | 🖱 SUBMIT PER BUTTON (falls du später Button nutzt)
    |--------------------------------------------------------------------------
    */
    document.addEventListener('click', async function (e) {

        if (!e.target.classList.contains('group-save-btn')) return;

        const form = e.target.closest('.score-form');

        const data = await submitScore(form);

        if (!data || !data.success) return;

        reloadGame(data.game_id);
        reloadGroup(data.group_id);

        // kleiner Delay wegen DOM update
        setTimeout(() => {
            scrollToNextGame(data.group_id);
        }, 100);
    });


    /*
    |--------------------------------------------------------------------------
    | ⌨ ENTER HANDLING
    |--------------------------------------------------------------------------
    */
    document.addEventListener('keydown', async function (e) {

        if (e.key !== 'Enter') return;

        const input = e.target;

        if (!input.classList.contains('group-score-input')) return;

        e.preventDefault();

        const form = input.closest('.score-form');

        const data = await submitScore(form);

        if (!data || !data.success) return;

        reloadGame(data.game_id);
        reloadGroup(data.group_id);

        // kleiner Delay wegen DOM update
        setTimeout(() => {
            scrollToNextGame(data.group_id);
        }, 100);
    });


    /*
    |--------------------------------------------------------------------------
    | 📥 FORM SUBMIT (WICHTIGSTER HANDLER)
    |--------------------------------------------------------------------------
    |
    | 🔥 DAS ist dein Hauptweg – alles andere optional
    |
    */
    document.addEventListener('submit', async function (e) {

        if (!e.target.classList.contains('score-form')) return;

        e.preventDefault();

        const form = e.target;

        const data = await submitScore(form);

        if (!data || !data.success) return;

        reloadGame(data.game_id);
        reloadGroup(data.group_id);

        // kleiner Delay wegen DOM update
        setTimeout(() => {
            scrollToNextGame(data.group_id);
        }, 100);
    });


    /*
    |--------------------------------------------------------------------------
    | 💾 SAVE ALL (optional)
    |--------------------------------------------------------------------------
    */
    const saveAllBtn = document.getElementById('save-all-groups-btn');

    if (saveAllBtn) {
        saveAllBtn.addEventListener('click', async function () {

            const forms = document.querySelectorAll('.score-form');

            for (const form of forms) {
                await submitScore(form);
            }

            location.reload(); // ok für bulk
        });
    }

}


/*
|--------------------------------------------------------------------------
| 🔄 BEST OF UPDATE
|--------------------------------------------------------------------------
*/
window.updateGroupBestOf = function (el, tournamentId) {

    const formData = new FormData();
    formData.append('best_of', el.value);

    fetch(`/tournaments/${tournamentId}/group-best-of`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
        },
        body: formData
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        })
        .catch(err => {
            console.error(err);
            alert('Fehler beim Speichern');
        });
};


/*
|--------------------------------------------------------------------------
| 🔄 GROUP RELOAD
|--------------------------------------------------------------------------
*/
window.reloadGroup = function (groupId) {

    if (!groupId) {
        console.warn('reloadGroup: groupId fehlt!');
        return;
    }

    console.log('Reloading group:', groupId);

    Promise.all([
        fetch(`/groups/${groupId}/table`).then(res => res.text()),
        fetch(`/groups/${groupId}/games`).then(res => res.text())
    ])
        .then(([tableHtml, gamesHtml]) => {

            const tableEl = document.querySelector(`[data-group-table="${groupId}"]`);
            const gamesEl = document.querySelector(`[data-group-games="${groupId}"]`);

            if (tableEl) tableEl.innerHTML = tableHtml;
            if (gamesEl) gamesEl.innerHTML = gamesHtml;

            // 🔁 Events neu initialisieren
            if (window.initGroups) {
                window.initGroups();
            }

        })
        .catch(err => {
            console.error('reloadGroup ERROR:', err);
        });
};


/*
|--------------------------------------------------------------------------
| 🔄 SINGLE GAME RELOAD
|--------------------------------------------------------------------------
*/
window.reloadGame = function (gameId) {

    fetch(`/games/${gameId}/html`)
        .then(res => res.text())
        .then(html => {

            const el = document.querySelector(`[data-game="${gameId}"]`);

            if (el) {
                el.outerHTML = html;
            }

            if (window.initGroups) {
                window.initGroups();
            }

        });
};
