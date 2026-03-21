<div x-data="{ openReset: false, openResetKo: false }" x-init="@if ($errors->getBag('reset')->has('confirm_name')) openReset = true @endif

@if ($errors->getBag('resetKo')->has('confirm_name')) openResetKo = true @endif"
    class="h-full bg-gray-900 border border-gray-800 rounded-xl p-6 shadow-lg">
    <div class="flex flex-col lg:flex-row lg:items-center gap-4">

        {{-- ============================================================
            TITEL
        ============================================================ --}}
        <div class="text-lg font-semibold text-white whitespace-nowrap">
            Aktionen:
        </div>


        {{-- ============================================================
            BUTTONS
        ============================================================ --}}
        <div class="flex flex-wrap gap-3">

            {{-- ========================================================
                DRAFT PHASE
            ======================================================== --}}
            @if ($tournament->status === 'draft')
                {{-- Spieler auslosen --}}
                <form method="POST" action="{{ route('tournaments.draw', $tournament) }}">
                    @csrf
                    <button class="bg-blue-600 hover:bg-blue-500 px-4 py-2 rounded-lg text-white transition">
                        🎲 Auslosen
                    </button>
                </form>

                {{-- Turnier starten --}}
                @php
                    $canStart = $tournament->players->count() >= 2;
                @endphp

                <form method="POST" action="{{ route('tournaments.start', $tournament) }}">
                    @csrf

                    <button type="submit" {{ !$canStart ? 'disabled' : '' }}
                        class="
            px-4 py-2 rounded-lg text-white transition
            {{ $canStart ? 'bg-green-600 hover:bg-green-500' : 'bg-gray-600 cursor-not-allowed opacity-50' }}
        "
                        title="{{ $canStart ? '' : 'Mindestens 2 Spieler erforderlich' }}">
                        ▶ Starten
                    </button>
                </form>
            @endif



            <button onclick="showView('players')" data-tab="players"
                class="tab-btn pb-2 text-gray-400 hover:text-white transition">
                👤 Spieler
            </button>

            <button onclick="showView('groups')" data-tab="groups"
                class="tab-btn pb-2 text-gray-400 hover:text-white transition">
                🟢 Gruppen
            </button>

            <button onclick="showView('bracket')" data-tab="bracket"
                class="tab-btn pb-2 text-gray-400 hover:text-white transition">
                🏆 Bracket
            </button>

            @if (config('app.autosim_enabled') && in_array($tournament->status, ['group_running', 'ko_running']))
                <div class="autosim-panel">

                    <div class="autosim-row">
                        <label>Speed: <span id="autosimSpeedLabel">1500ms</span></label>
                        <input type="range" id="autosimSpeed" min="1000" max="3500" step="100"
                            value="1500">
                    </div>

                    <div class="autosim-row">
                        <button id="autosimStart">▶ Start</button>
                        <button id="autosimStop" disabled>⏹ Stop</button>
                    </div>

                    <div class="autosim-status" id="autosimStatus">
                        Status: Idle
                    </div>

                </div>
            @endif

            {{-- ========================================================
                GRUPPENPHASE → KO START
            ======================================================== --}}
            @if ($tournament->status === 'group_running')
                @php
                    $unfinished = $tournament->games->whereNotNull('group_id')->whereNull('winner_id')->count();

                    $canFinish = $unfinished === 0;
                @endphp

                <form method="POST" action="{{ route('tournaments.finishGroups', $tournament) }}">
                    @csrf

                    <button type="submit" {{ !$canFinish ? 'disabled' : '' }}
                        class="
                px-4 py-2 rounded-lg text-white transition
                {{ $canFinish
                    ? 'bg-purple-600 hover:bg-purple-500'
                    : 'bg-gray-600 cursor-not-allowed opacity-50 hover:bg-gray-600' }}
            "
                        title="{{ $canFinish ? '' : 'Gruppenspiele noch nicht vollständig' }}">
                        🏁 KO-Phase starten
                    </button>
                </form>
            @endif


            {{-- ========================================================
                RESET (GESAMTES TURNIER)
            ======================================================== --}}
            @if ($tournament->status !== 'draft')
                <button type="button" @click="openReset = true"
                    class="bg-red-600 hover:bg-red-500 px-4 py-2 rounded-lg text-white transition">
                    ⛔ Reset
                </button>
            @endif


            {{-- ========================================================
                KO RESET (WICHTIG → MIT MODAL)
            ======================================================== --}}
            @if ($tournament->status === 'ko_running' && $tournament->mode === 'group_ko')
                <button type="button" @click="openResetKo = true"
                    class="bg-red-700 hover:bg-red-600 px-4 py-2 rounded-lg text-white">
                    🔁 KO-Phase zurücksetzen
                </button>
            @endif


            {{-- ========================================================
                TURNIER ABGESCHLOSSEN
            ======================================================== --}}
            @if ($tournament->status === 'finished')
                <form method="POST" action="{{ route('tournaments.reopen', $tournament) }}"
                    onsubmit="return confirm('Turnier wirklich wieder öffnen?');">
                    @csrf
                    <button class="bg-yellow-600 hover:bg-yellow-500 px-4 py-2 rounded-lg text-white">
                        🔓 Turnier wieder öffnen
                    </button>
                </form>
            @endif

        </div>

    </div>


    {{-- ============================================================
        MODAL: KOMPLETTER RESET
    ============================================================ --}}
    <div x-show="openReset" x-transition class="fixed inset-0 bg-black/60 flex items-center justify-center z-50">

        <div class="bg-gray-900 p-6 rounded-xl border border-gray-700 w-full max-w-md">

            <h3 class="text-lg font-semibold text-white mb-4">
                Turnier wirklich zurücksetzen?
            </h3>

            <p class="text-sm text-gray-400 mb-4">
                Alle Spiele und Gruppen werden gelöscht.<br>
                Gib zur Bestätigung den Turniernamen ein:
            </p>

            <form method="POST" action="{{ route('tournaments.reset', $tournament) }}">
                @csrf

                {{-- ❌ Fehler anzeigen --}}
                @error('confirm_name', 'reset')
                    <div class="text-red-400 text-sm mb-2">
                        {{ $message }}
                    </div>
                @enderror

                {{-- 🧠 Input --}}
                <input type="text" name="confirm_name" value="{{ old('confirm_name') }}"
                    placeholder="{{ $tournament->name }}"
                    class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-white mb-4" required>

                <div class="flex justify-end gap-3">
                    <button type="button" @click="openReset = false" class="bg-gray-700 px-4 py-2 rounded text-white">
                        Abbrechen
                    </button>

                    <button type="submit" class="bg-red-600 hover:bg-red-500 px-4 py-2 rounded text-white">
                        Endgültig zurücksetzen
                    </button>
                </div>
            </form>

        </div>
    </div>


    {{-- ============================================================
        MODAL: KO RESET
    ============================================================ --}}
    <div x-show="openResetKo" x-transition class="fixed inset-0 bg-black/60 flex items-center justify-center z-50">

        <div class="bg-gray-900 p-6 rounded-xl border border-gray-700 w-full max-w-md">

            <h3 class="text-lg font-semibold text-white mb-4">
                KO-Phase wirklich zurücksetzen?
            </h3>

            <p class="text-sm text-gray-400 mb-4">
                Alle KO-Spiele werden gelöscht.<br>
                Gruppenergebnisse bleiben erhalten.<br><br>
                Gib zur Bestätigung den Turniernamen ein:
            </p>

            <form method="POST" action="{{ route('tournaments.resetKo', $tournament) }}">
                @csrf

                {{-- ❌ Fehler anzeigen --}}
                @error('confirm_name', 'resetKo')
                    <div class="text-red-400 text-sm mb-2">
                        {{ $message }}
                    </div>
                @enderror

                {{-- 🧠 Input --}}
                <input type="text" name="confirm_name" value="{{ old('confirm_name') }}"
                    placeholder="{{ $tournament->name }}"
                    class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-white mb-4" required>

                <div class="flex justify-end gap-3">
                    <button type="button" @click="openResetKo = false"
                        class="bg-gray-700 px-4 py-2 rounded text-white">
                        Abbrechen
                    </button>

                    <button type="submit" class="bg-red-600 hover:bg-red-500 px-4 py-2 rounded text-white">
                        KO-Phase zurücksetzen
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>
