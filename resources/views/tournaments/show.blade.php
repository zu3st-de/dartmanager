<x-app-layout>
    {{-- Gesamt-Layout mit vertikalem Abstand --}}
    <div class="space-y-8 py-8">

        {{-- Grid: links Header (1/3), rechts Actions (2/3) --}}
        <div class="h-full grid grid-cols-1 lg:grid-cols-3 gap-8">

            <!-- 🔹 Turnier-Header (1/3) -->
            <div class="bg-gray-900 border border-gray-800 rounded-xl p-6 shadow-lg">

                {{-- Titel + Status --}}
                <div class="flex justify-between items-center">

                    {{-- Turniername --}}
                    <h1 class="text-2xl font-semibold text-white">
                        {{ $tournament->name }}
                    </h1>

                    {{-- Status-Badge mit dynamischer Farbe je nach Zustand --}}
                    <span
                        class="px-3 py-1 text-xs rounded-full

                            {{-- Draft (noch nicht gestartet) --}}
                            @if ($tournament->status === 'draft') bg-yellow-500/20 text-yellow-400

                            {{-- Gruppenphase läuft --}}
                            @elseif($tournament->status === 'group_running') 
                                bg-blue-500/20 text-blue-400

                            {{-- KO-Phase läuft --}}
                            @elseif($tournament->status === 'ko_running') 
                                bg-purple-500/20 text-purple-400

                            {{-- Turnier abgeschlossen --}}
                            @elseif($tournament->status === 'finished') 
                                bg-green-500/20 text-green-400 @endif
                        ">

                        {{-- Status lesbar machen (snake_case → Text) --}}
                        {{ ucfirst(str_replace('_', ' ', $tournament->status)) }}
                    </span>
                </div>

                {{-- Anzeige des Turniermodus (z.B. ko / group_ko) --}}
                <div class="text-gray-400 mt-2 text-sm">
                    Modus: {{ $tournament->mode }}
                </div>

            </div>

            <!-- 🔹 Actions-Bereich (2/3) -->
            <div class="lg:col-span-2">

                {{-- Buttons / Steuerung (Start, Reset, etc.) --}}
                @include('tournaments.partials.actions')

            </div>

        </div>

        {{-- 🔽 Weitere Bereiche als Partials ausgelagert --}}

        {{-- 🔹 VIEWS WRAPPER --}}
        <div id="view-players" class="view-section">
            @include('tournaments.partials.players')
        </div>

        <div id="view-groups" class="view-section hidden">

            @if ($tournament->status !== 'draft')
                @include('tournaments.partials.groups')
            @else
                <div class="text-gray-400 text-center py-10">
                    Gruppenphase noch nicht verfügbar
                </div>
            @endif

        </div>

        <div id="view-bracket" class="view-section hidden">

            @if ($tournament->status !== 'draft')
                @include('tournaments.partials.knockout')
            @else
                <div class="text-gray-400 text-center py-10">
                    KO-Phase noch nicht gestartet
                </div>
            @endif

        </div>

        {{-- Gewinneranzeige --}}
        @include('tournaments.partials.winner')

    </div>

</x-app-layout>
<script>
    window.tournamentStatus = "{{ $tournament->status }}";
</script>
