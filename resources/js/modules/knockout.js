/**

* ---
* 🧠 KO PHASE JAVASCRIPT (STABIL & EINFACH)
* ---
*
* Prinzip:
* 👉 Nach jeder Aktion werden ALLE sichtbaren Spiele neu geladen
*
* Vorteile:
* * keine Speziallogik für Finale / Platz 3
* * keine Abhängigkeiten (next / related)
* * keine Inkonsistenzen
* * super stabil
*
* Nachteil:
* * etwas mehr Requests (aber völlig ok für kleine Brackets)
*
* ---

*/

export function initKnockout() {

    /**
     * ------------------------------------------------------------------------
     * 🔥 POST REQUEST (Save / Reset)
     * ------------------------------------------------------------------------
     *
     * Sendet das Formular an Laravel (Score oder Reset)
     *
     */
    async function post(form) {

        const response = await fetch(form.dataset.url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new FormData(form)
        });

        return response.ok;
    }


    /**
     * ------------------------------------------------------------------------
     * 🔄 EIN SPIEL NEU LADEN
     * ------------------------------------------------------------------------
     *
     * Holt HTML vom Server und ersetzt den Inhalt im DOM
     *
     */
    async function reloadGame(gameId) {

        const response = await fetch(`/games/${gameId}/html`);
        if (!response.ok) return;

        const html = await response.text();

        const current = document.querySelector(
            `[data-game-id="${gameId}"]`
        );

        if (current) {
            current.innerHTML = html;
        }
    }


    /**
     * ------------------------------------------------------------------------
     * 🔥 ALLE SPIELE NEU LADEN (KEY FEATURE)
     * ------------------------------------------------------------------------
     *
     * Statt komplizierter Logik (next / related)
     * → einfach alles neu laden
     *
     */
    async function reloadAllGames() {

        const games = document.querySelectorAll('[data-game-id]');

        const promises = [];

        for (const gameEl of games) {

            const id = gameEl.dataset.gameId;

            if (id) {
                promises.push(reloadGame(id));
            }
        }

        await Promise.all(promises);
    }


    /**
     * ------------------------------------------------------------------------
     * 🚀 ZENTRALE SUBMIT-LOGIK
     * ------------------------------------------------------------------------
     *
     * Wird für:
     * - Save (Score)
     * - Reset
     *
     */
    async function handleSubmit(form) {

        const gameId = form.dataset.gameId;

        // 🔍 Validierung (nur bei Score)
        if (form.classList.contains('score-form')) {

            const inputs = form.querySelectorAll('.score-input');

            if (inputs.length === 2) {

                const val1 = inputs[0].value;
                const val2 = inputs[1].value;

                // beide Felder müssen gesetzt sein
                if (!val1 || !val2) return;
            }
        }

        const success = await post(form);
        if (!success) return;

        // 🔥 HIER passiert die Magie
        await reloadAllGames();
    }


    /**
     * ------------------------------------------------------------------------
     * 🖱 CLICK HANDLER (Save + Reset)
     * ------------------------------------------------------------------------
     *
     * Reagiert auf:
     * - Save Button
     * - Reset Button
     *
     */
    document.addEventListener('click', async function (e) {

        const form = e.target.closest('.score-form, .reset-form');
        if (!form) return;

        e.preventDefault();

        await handleSubmit(form);
    });


    /**
     * ------------------------------------------------------------------------
     * ⌨️ ENTER HANDLER (nur Score)
     * ------------------------------------------------------------------------
     *
     * Enter triggert Speichern
     * → aber kontrolliert (kein nativer Submit)
     *
     */
    document.addEventListener('keydown', async function (e) {

        if (e.key !== 'Enter') return;

        const input = e.target;

        if (!input.classList.contains('score-input')) return;

        e.preventDefault();

        const form = input.closest('.score-form');
        if (!form) return;

        await handleSubmit(form);
    });


    /**
     * ------------------------------------------------------------------------
     * 🚫 NATIVEN FORM SUBMIT BLOCKIEREN
     * ------------------------------------------------------------------------
     *
     * Verhindert:
     * - Browser Submit
     * - Enter Submit
     * - Mobile Auto Submit
     *
     */
    document.addEventListener('submit', function (e) {

        if (
            e.target.classList.contains('score-form') ||
            e.target.classList.contains('reset-form')
        ) {
            e.preventDefault();
        }
    });


}
