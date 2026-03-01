export function initPlayers() {

    const form = document.getElementById('playerForm');
    if (!form) return;

    const input = document.getElementById('playerInput');
    const list = document.getElementById('playersList');
    const startForm = document.getElementById('start-form');
    const playerCount = document.getElementById('player-count');

    input?.focus();

    form.addEventListener('submit', async function (e) {

        e.preventDefault();

        const name = input.value.trim();
        if (!name) return;

        const button = form.querySelector('button[type="submit"]');
        button.disabled = true;

        try {

            const response = await fetch(form.dataset.url, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": document
                        .querySelector('meta[name="csrf-token"]')
                        ?.content,
                    "Accept": "application/json"
                },
                body: JSON.stringify({ name })
            });

            if (!response.ok) {
                throw new Error("Server error");
            }

            const player = await response.json();

            // Spieler zur Liste hinzufügen
            const div = document.createElement('div');
            div.className = "border rounded px-3 py-2";
            div.textContent = player.name;

            list?.appendChild(div);

            // Teilnehmer-Zähler aktualisieren
            if (playerCount) {

                let count = parseInt(playerCount.dataset.count || "0");
                count++;

                playerCount.dataset.count = count;
                playerCount.textContent = `Teilnehmer (${count})`;

                // Start-Button anzeigen (ab 2 Spielern)
                if (count >= 2 && startForm) {
                    startForm.classList.remove('hidden');
                }
            }

            input.value = "";
            input.focus();

        } catch (error) {

            console.error(error);
            alert("Fehler beim Hinzufügen des Spielers.");

        } finally {

            button.disabled = false;

        }
    });
}