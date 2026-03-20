{{-- 🔹 Container nur sichtbar, wenn showPlayers = true (Alpine.js) --}}
<div x-show="showPlayers" x-transition>

    {{-- Haupt-Card --}}
    <div class="bg-gray-900 border border-gray-800 rounded-xl p-6 shadow-lg">

        {{-- 🔹 Überschrift + Spieleranzahl --}}
        <h2 id="player-count" data-count="{{ $tournament->players->count() }}"
            class="text-lg font-semibold mb-4 text-white">

            Teilnehmer ({{ $tournament->players->count() }})
        </h2>

        {{-- 🔹 Liste aller Spieler --}}
        <div id="playersList" class="space-y-2 mb-4">

            {{-- Spieler nach Seed sortiert --}}
            @foreach ($tournament->players->sortBy('seed') as $player)
                {{-- Alpine State pro Spieler (Edit-Modus) --}}
                <div x-data="{ editing: false }"
                    class="bg-gray-800 rounded-lg px-3 py-2 border border-gray-700 flex justify-between items-center">

                    {{-- 🔹 ANZEIGE-MODUS --}}
                    <div class="flex justify-between items-center w-full" x-show="!editing">

                        <div class="flex items-center gap-3">

                            {{-- Seed / Setzliste --}}
                            <span class="bg-blue-600 text-white text-xs px-2 py-1 rounded">
                                #{{ $player->seed ?? '-' }}
                            </span>

                            {{-- Spielername --}}
                            <span class="text-white">
                                {{ $player->name }}
                            </span>

                        </div>

                        {{-- Aktionen (Edit / Delete) --}}
                        <div class="flex gap-2">

                            {{-- Edit-Button → wechselt in Edit-Modus --}}
                            <button @click="editing = true" class="text-yellow-400 hover:text-yellow-300 text-sm">
                                ✏️
                            </button>

                            {{-- Löschen nur im Draft erlaubt --}}
                            @if ($tournament->status === 'draft')
                                <form action="{{ route('players.destroy', $player) }}" method="POST"
                                    onsubmit="return confirm('Spieler wirklich löschen?');">

                                    @csrf
                                    @method('DELETE')

                                    <button type="submit" class="text-red-500 hover:text-red-400 text-sm">
                                        ❌
                                    </button>
                                </form>
                            @endif

                        </div>
                    </div>

                    {{-- 🔹 EDIT-MODUS --}}
                    <form x-show="editing" x-transition action="{{ route('players.update', $player) }}" method="POST"
                        class="flex gap-2 items-center w-full">

                        @csrf
                        @method('PATCH')

                        {{-- Eingabefeld für neuen Namen --}}
                        <input type="text" name="name" value="{{ $player->name }}"
                            class="bg-gray-700 border border-gray-600 rounded px-2 py-1 text-white text-sm w-full focus:ring-2 focus:ring-blue-500 focus:outline-none"
                            autofocus>

                        {{-- Speichern --}}
                        <button type="submit" class="text-green-400 hover:text-green-300 text-sm">
                            💾
                        </button>

                        {{-- Abbrechen --}}
                        <button type="button" @click="editing = false"
                            class="text-gray-400 hover:text-gray-300 text-sm">
                            ✖
                        </button>
                    </form>

                </div>
            @endforeach
        </div>

        {{-- 🔹 Spieler hinzufügen nur im Draft --}}
        @if ($tournament->status === 'draft')
            {{-- Einzelnen Spieler hinzufügen --}}
            <form id="playerForm" data-url="{{ route('tournaments.players.store', $tournament) }}" class="flex gap-2">

                <input type="text" id="playerInput" placeholder="Spielername eingeben" required autocomplete="off"
                    class="flex-1 bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white focus:ring-2 focus:ring-blue-500 focus:outline-none">

                <button type="submit" class="bg-blue-600 hover:bg-blue-500 transition px-4 py-2 rounded-lg text-white">
                    Hinzufügen
                </button>

            </form>

            {{-- 🔹 Bulk-Import von Spielern --}}
            <div class="mt-4">

                <label class="block text-sm text-gray-400 mb-2">
                    Mehrere Spieler einfügen (eine Zeile pro Spieler)
                </label>

                <form method="POST" action="{{ route('tournaments.bulkPlayers', $tournament) }}">
                    @csrf

                    {{-- Mehrzeiliges Textfeld für Liste --}}
                    <textarea name="bulk_players" rows="6" placeholder="Max Mustermann
Peter Schmidt
Lisa Müller"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white focus:ring-2 focus:ring-blue-500 focus:outline-none"></textarea>

                    {{-- Submit --}}
                    <button type="submit" class="mt-3 bg-blue-600 hover:bg-blue-500 px-4 py-2 rounded-lg text-white">
                        📋 Liste importieren
                    </button>
                </form>

            </div>
        @endif

    </div>
</div>
