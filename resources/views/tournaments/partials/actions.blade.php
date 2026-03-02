<div
    x-data="{ openReset: false }"
    class="h-full bg-gray-900 border border-gray-800 rounded-xl p-6 shadow-lg">

    <div class="flex flex-col lg:flex-row lg:items-center gap-4">

        <!-- Titel -->
        <div class="text-lg font-semibold text-white whitespace-nowrap">
            Aktionen:
        </div>

        <!-- Buttons -->
        <div class="flex flex-wrap gap-3">

            {{-- DRAFT --}}
            @if($tournament->status === 'draft')

            <form method="POST" action="{{ route('tournaments.draw', $tournament) }}">
                @csrf
                <button class="bg-blue-600 hover:bg-blue-500 px-4 py-2 rounded-lg text-white transition">
                    🎲 Spieler auslosen
                </button>
            </form>

            @if($tournament->players->count() >= 2)
            <form method="POST" action="{{ route('tournaments.start', $tournament) }}">
                @csrf
                <button class="bg-green-600 hover:bg-green-500 px-4 py-2 rounded-lg text-white transition">
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
                class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition">
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
                <button class="bg-purple-600 hover:bg-purple-500 px-4 py-2 rounded-lg text-white transition">
                    🏁 KO-Phase starten
                </button>
            </form>
            @else
            <div class="text-sm text-gray-400 self-center">
                Gruppenspiele noch nicht vollständig
            </div>
            @endif

            @endif


            {{-- RESET --}}
            @if($tournament->status !== 'draft')
            <button
                type="button"
                @click="openReset = true"
                class="bg-red-600 hover:bg-red-500 px-4 py-2 rounded-lg text-white transition">
                ⛔ Reset
            </button>
            @endif


            {{-- FINISHED --}}
            @if($tournament->status === 'finished')
            <div class="text-green-400 text-sm self-center">
                Turnier abgeschlossen
            </div>

            <form method="POST"
                action="{{ route('tournaments.reopen', $tournament) }}"
                onsubmit="return confirm('Turnier wirklich wieder öffnen?');">
                @csrf
                <button class="bg-yellow-600 hover:bg-yellow-500 px-4 py-2 rounded-lg text-white">
                    🔓 Turnier wieder öffnen
                </button>
            </form>
            @endif

        </div>

    </div>


    <!-- MODAL -->
    <div
        x-show="openReset"
        x-transition
        class="fixed inset-0 bg-black/60 flex items-center justify-center z-50">
        <div class="bg-gray-900 p-6 rounded-xl border border-gray-700 w-full max-w-md">

            <h3 class="text-lg font-semibold text-white mb-4">
                Turnier wirklich zurücksetzen?
            </h3>

            <p class="text-sm text-gray-400 mb-4">
                Alle Spiele werden gelöscht.<br>
                Gib zur Bestätigung den Turniernamen ein:
            </p>

            <form method="POST" action="{{ route('tournaments.reset', $tournament) }}">
                @csrf

                <input
                    type="text"
                    name="confirm_name"
                    placeholder="{{ $tournament->name }}"
                    class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-white mb-4"
                    required>

                <div class="flex justify-end gap-3">
                    <button
                        type="button"
                        @click="openReset = false"
                        class="bg-gray-700 px-4 py-2 rounded text-white">
                        Abbrechen
                    </button>

                    <button
                        type="submit"
                        class="bg-red-600 hover:bg-red-500 px-4 py-2 rounded text-white">
                        Endgültig zurücksetzen
                    </button>
                </div>
            </form>

        </div>
    </div>

</div>