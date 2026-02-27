<div class="bg-white shadow rounded p-6 mb-6">

    <h2 class="text-lg font-semibold mb-4">
        Teilnehmer ({{ $tournament->players->count() }})
    </h2>

    {{-- Teilnehmerliste --}}
    <div id="playersList" class="space-y-2 mb-4">

        @foreach($tournament->players as $player)

        <div class="border rounded px-3 py-2">
            {{ $player->name }}
        </div>

        @endforeach

    </div>


    {{-- Formular --}}
    @if($tournament->status === 'draft')

    <form id="playerForm" class="flex gap-2">

        @csrf

        <input
            type="text"
            name="name"
            id="playerInput"
            placeholder="Spielername eingeben"
            required
            autocomplete="off"
            class="border rounded px-3 py-2 flex-1">

        <button
            type="submit"
            class="bg-blue-600 text-white px-4 py-2 rounded">

            Hinzufügen

        </button>

    </form>

    @endif

</div>


<script>
    document.addEventListener('DOMContentLoaded', function() {

        const form = document.getElementById('playerForm');
        const input = document.getElementById('playerInput');
        const list = document.getElementById('playersList');

        if (!form) return;

        input.focus();

        form.addEventListener('submit', async function(e) {

            e.preventDefault();

            const name = input.value.trim();

            if (!name) return;

            const response = await fetch(
                "{{ route('tournaments.addPlayer', $tournament) }}", {
                    method: "POST",

                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": document.querySelector('input[name=_token]').value,
                        "Accept": "application/json"
                    },

                    body: JSON.stringify({
                        name
                    })
                }
            );

            if (!response.ok) {
                alert("Fehler beim Hinzufügen");
                return;
            }

            const player = await response.json();

            // neuen Spieler zur Liste hinzufügen
            const div = document.createElement('div');

            div.className = "border rounded px-3 py-2";
            div.textContent = player.name;

            list.appendChild(div);
            // Teilnehmer-Anzahl erhöhen
            const heading = document.querySelector('h2');
            const currentCountMatch = heading.textContent.match(/\((\d+)\)/);

            if (currentCountMatch) {

                let count = parseInt(currentCountMatch[1]);
                count++;

                heading.textContent =
                    `Teilnehmer (${count})`;

                // Start-Button anzeigen wenn >= 2
                if (count >= 2) {

                    const startForm =
                        document.getElementById('start-form');

                    if (startForm) {
                        startForm.classList.remove('hidden');
                    }

                }

            }
            // Feld leeren und Fokus behalten
            input.value = "";
            input.focus();

        });

    });
</script>