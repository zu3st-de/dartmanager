@if($tournament->status === 'draft')

<div class="bg-gray-900 border border-gray-800 rounded-xl p-6 shadow-lg">

    <h2 id="player-count"
        data-count="{{ $tournament->players->count() }}"
        class="text-lg font-semibold mb-4 text-white">
        Teilnehmer ({{ $tournament->players->count() }})
    </h2>

    <div id="playersList" class="space-y-2 mb-4">
        @foreach($tournament->players as $player)
        <div class="bg-gray-800 rounded-lg px-3 py-2 border border-gray-700">
            {{ $player->name }}
        </div>
        @endforeach
    </div>

    <form id="playerForm"
        data-url="{{ route('tournaments.addPlayer', $tournament) }}"
        class="flex gap-2">

        <input
            type="text"
            id="playerInput"
            placeholder="Spielername eingeben"
            required
            autocomplete="off"
            class="flex-1 bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white focus:ring-2 focus:ring-blue-500 focus:outline-none">

        <button
            type="submit"
            class="bg-blue-600 hover:bg-blue-500 transition px-4 py-2 rounded-lg text-white">
            Hinzufügen
        </button>

    </form>

</div>

@endif