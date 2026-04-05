/*
|--------------------------------------------------------------------------
| 🎯 BRACKET.JS (FINAL VERSION)
|--------------------------------------------------------------------------
|
| Features:
| - Best-of ändern (AJAX)
| - Score speichern (AJAX)
| - Ergebnis löschen (AJAX)
| - Einzelnes Spiel neu laden (kein Layout-Bruch)
|
| WICHTIG:
| - Server rendert HTML
| - JS ersetzt nur das betroffene Spiel
|
*/

/*
|--------------------------------------------------------------------------
| 🔧 HELPER: CSRF TOKEN
|--------------------------------------------------------------------------
*/
function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]').content;
}

/*
|--------------------------------------------------------------------------
| 🔄 GAME NEU LADEN (ZENTRAL!)
|--------------------------------------------------------------------------
|
| Lädt das HTML eines Spiels neu vom Server
| und ersetzt es im DOM
|
*/
function reloadGame(gameId) {

    if (!gameId) return; fetch(`/games/${gameId}/next`)

    fetch(`/games/${gameId}/reload`)
        .then(res => res.text())
        .then(html => {

            const wrapper = document.querySelector(`[data-game="${gameId}"]`);
            if (!wrapper) return;

            wrapper.outerHTML = html;
        })
        .catch(err => {
            console.error('reloadGame ERROR:', err);
        });
}

/*
|--------------------------------------------------------------------------
| 🏆 BEST-OF ÄNDERN (GLOBAL)
|--------------------------------------------------------------------------
|
| Wird direkt aus Blade aufgerufen (onchange)
|
*/
window.updateBestOf = function (el, tournamentId, round) {

    fetch(`/tournaments/${tournamentId}/round/${round}/best-of`, {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken()
        },
        body: JSON.stringify({
            best_of: el.value
        })
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {

                // ✅ visuelles Feedback
                el.style.border = '2px solid #22c55e';
                setTimeout(() => el.style.border = '', 800);

            } else {
                alert(data.message || 'Fehler');
            }
        });
};

/*
|--------------------------------------------------------------------------
| 🚀 DOM READY
|--------------------------------------------------------------------------
*/
document.addEventListener('DOMContentLoaded', () => {

    /*
    |--------------------------------------------------------------------------
    | 📝 SCORE FORM (AJAX)
    |--------------------------------------------------------------------------
    */
    document.addEventListener('submit', function (e) {

        if (!e.target.classList.contains('score-form')) return;

        e.preventDefault();

        const form = e.target;
        const url = form.action;
        const gameId = form.dataset.game;

        const formData = new FormData(form);

        fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
            .then(res => res.json())
            .then(data => {

                if (data.success) {

                    // KO → chain reload
                    if (data.reload) {
                        data.reload.forEach(id => reloadGame(id));
                    }

                    // Nur wenn explizit KO chain
                    if (data.next_game_id) {
                        reloadGameChain(gameId);
                    }
                } else {
                    alert(data.message || 'Fehler');
                }

            })
            .catch(() => alert('Serverfehler'));

    });


    /*
    |--------------------------------------------------------------------------
    | 🗑 RESET FORM (AJAX)
    |--------------------------------------------------------------------------
    */
    document.addEventListener('submit', function (e) {

        if (!e.target.classList.contains('reset-form')) return;

        e.preventDefault();

        if (!confirm('Ergebnis wirklich löschen?')) return;

        const form = e.target;
        const url = form.action;
        const gameId = form.dataset.game;

        fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(res => res.json())
            .then(data => {

                if (data.success) {

                    reloadGameChain(gameId);

                } else {
                    alert(data.message || 'Fehler');
                }

            })
            .catch(() => alert('Serverfehler'));

    });

});

function reloadGameChain(gameId) {

    if (!gameId) return;

    // 🔹 aktuelles Spiel neu laden
    reloadGame(gameId);

    // 🔹 danach nächstes Spiel holen
    fetch(`/games/${gameId}/next`)
        .then(res => res.json())
        .then(data => {

            if (data.next_game_id) {

                // 🔥 recursion!
                setTimeout(() => {
                    reloadGameChain(data.next_game_id);
                }, 50); // kleiner Delay für DOM Stabilität

            }

        });
}