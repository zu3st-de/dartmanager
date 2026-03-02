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
            const wrapper = document.createElement('div');

            wrapper.innerHTML = `
<div 
    x-data="{ editing: false }"
    class="bg-gray-800 rounded-lg px-3 py-2 border border-gray-700 flex justify-between items-center"
>

    <div class="flex justify-between items-center w-full" x-show="!editing" x-cloak>

        <span class="text-white">
            ${player.name}
        </span>

        <div class="flex gap-2">

            <button 
                type="button"
                @click="editing = true"
                class="text-yellow-400 hover:text-yellow-300 text-sm">
                ✏️
            </button>

            <form 
                action="/players/${player.id}" 
                method="POST"
                onsubmit="return confirm('Spieler wirklich löschen?');"
            >
                <input type="hidden" name="_token" value="${document.querySelector('meta[name="csrf-token"]').content}">
                <input type="hidden" name="_method" value="DELETE">

                <button 
                    type="submit"
                    class="text-red-500 hover:text-red-400 text-sm">
                    ❌
                </button>
            </form>

        </div>
    </div>

    <form 
        x-show="editing"
        x-cloak
        x-transition
        @keydown.escape.window="editing = false"
        action="/players/${player.id}" 
        method="POST"
        class="flex gap-2 items-center w-full"
    >
        <input type="hidden" name="_token" value="${document.querySelector('meta[name="csrf-token"]').content}">
        <input type="hidden" name="_method" value="PATCH">

        <input
            type="text"
            name="name"
            value="${player.name}"
            class="bg-gray-700 border border-gray-600 rounded px-2 py-1 text-white text-sm w-full focus:ring-2 focus:ring-blue-500 focus:outline-none"
        >

        <button 
            type="submit"
            class="text-green-400 hover:text-green-300 text-sm">
            💾
        </button>

        <button 
            type="button"
            @click="editing = false"
            class="text-gray-400 hover:text-gray-300 text-sm">
            ✖
        </button>
    </form>

</div>
`;

            const newElement = wrapper.firstElementChild;
            list.appendChild(newElement);

            // 🔥 WICHTIG: Alpine neu initialisieren
            if (window.Alpine) {
                Alpine.initTree(newElement);
            }

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