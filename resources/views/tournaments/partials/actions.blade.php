<div class="bg-gray-900 border border-gray-800 rounded-xl p-6 shadow-lg">

    <h2 class="text-lg font-semibold mb-4 text-white">
        Aktionen
    </h2>

    <div class="flex flex-wrap gap-3">

        {{-- DRAFT --}}
        @if($tournament->status === 'draft')

        {{-- Auslosen --}}
        <form method="POST" action="{{ route('tournaments.draw', $tournament) }}">
            @csrf
            <button class="bg-blue-600 hover:bg-blue-500 transition px-4 py-2 rounded-lg text-white">
                🎲 Spieler auslosen
            </button>
        </form>

        {{-- Starten --}}
        @if($tournament->players->count() >= 2)
        <form method="POST" action="{{ route('tournaments.start', $tournament) }}">
            @csrf
            <button class="bg-green-600 hover:bg-green-500 transition px-4 py-2 rounded-lg text-white">
                ▶ Turnier starten
            </button>
        </form>
        @else
        <div class="text-sm text-gray-400 self-center">
            Mindestens 2 Spieler erforderlich
        </div>
        @endif

        @endif
        {{-- Spieler anzeigen --}}
        @if($tournament->status !== 'draft')
        <button
            type="button"
            @click="showPlayers = !showPlayers"
            class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded">
            <span x-show="!showPlayers">Spieler anzeigen</span>
            <span x-show="showPlayers">Spieler ausblenden</span>
        </button>
        @endif

        {{-- GRUPPENPHASE --}}
        @if($tournament->status === 'group_running')

        @php
        $unfinished = $tournament->games
        ->whereNotNull('group_id')
        ->whereNull('winner_id')
        ->count();
        @endphp

        @if($unfinished === 0)

        <form method="POST" action="{{ route('tournaments.finishGroups', $tournament) }}">
            @csrf
            <button class="bg-purple-600 hover:bg-purple-500 transition px-4 py-2 rounded-lg text-white">
                🏁 KO-Phase starten
            </button>
        </form>

        @else

        <div class="text-sm text-gray-400 self-center">
            Gruppenspiele noch nicht vollständig
        </div>

        @endif

        @endif


        {{-- FINISHED --}}
        @if($tournament->status === 'finished')
        <div class="text-green-400 text-sm self-center">
            Turnier abgeschlossen
        </div>
        @endif

    </div>

</div>