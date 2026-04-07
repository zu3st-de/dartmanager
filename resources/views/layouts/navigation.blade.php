<nav class="bg-gray-900 border-b border-gray-800 px-6 py-3">
    <div class="flex items-center justify-between">

        {{-- Linke Seite --}}
        <div class="flex items-center gap-8">

            {{-- Logo --}}
            <a href="{{ route('tournaments.index') }}" class="flex items-center gap-3">

                <img src="{{ asset('images/dart-manager-horizontal-normal.png') }}"
                    srcset="{{ asset('images/dart-manager-horizontal@2x.png') }} 2x" height="50"
                    alt="Dart Manager Logo">

            </a>

            {{-- Alle Turniere --}}
            <a href="{{ route('tournaments.index') }}"
                class="text-gray-300 hover:text-white text-sm font-semibold transition">
                Alle Turniere
            </a>

            <div class="relative"
                x-data="{
                    open: false,
                    rotation: {{ $tvRotationTime ?? 20 }},
                    closeTimer: null,
                    show() {
                        clearTimeout(this.closeTimer);
                        this.open = true;
                    },
                    hide() {
                        this.closeTimer = setTimeout(() => {
                            this.open = false;
                        }, 180);
                    }
                }">
                <div x-cloak x-show="open" x-transition.opacity.duration.150ms
                    @mouseenter="show()" @mouseleave="hide()"
                    class="absolute left-1/2 top-full z-20 mt-3 w-64 -translate-x-1/2 rounded-2xl border border-gray-700 bg-gray-900/95 p-4 shadow-2xl backdrop-blur">
                    <form method="POST" action="{{ route('tv.rotation-time') }}" class="space-y-3">
                        @csrf
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-sm font-medium text-white">Rotation</span>
                            <span class="text-sm text-emerald-400" x-text="rotation + 's'"></span>
                        </div>

                        <input type="range" name="rotation_time" min="3" max="45" step="1"
                            x-model="rotation"
                            class="h-2 w-full cursor-pointer appearance-none rounded-full bg-gray-700 accent-emerald-500"
                            {{ ($hasTvTournaments ?? false) ? '' : 'disabled' }}>

                        <div class="flex items-center justify-between gap-3">
                            <span class="text-xs text-gray-400">
                                @if ($hasTvTournaments ?? false)
                                    Für alle TV-Turniere
                                @else
                                    Erst ein Turnier fürs TV markieren
                                @endif
                            </span>

                            <button type="submit"
                                class="inline-flex items-center rounded-full border border-emerald-500 px-3 py-1.5 text-xs font-medium text-emerald-400 transition hover:bg-emerald-500/10 disabled:cursor-not-allowed disabled:border-gray-700 disabled:text-gray-500"
                                {{ ($hasTvTournaments ?? false) ? '' : 'disabled' }}>
                                Speichern
                            </button>
                        </div>
                    </form>
                </div>

                <a href="{{ url('/tv') }}" target="_blank" rel="noopener" @mouseenter="show()" @mouseleave="hide()"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-gray-700 text-gray-300 transition hover:border-emerald-500 hover:text-emerald-400"
                    title="TV Rotation in neuem Fenster öffnen" aria-label="TV Rotation in neuem Fenster öffnen">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                        stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="3" y="5" width="18" height="12" rx="2"></rect>
                        <path d="M8 21h8"></path>
                        <path d="M12 17v4"></path>
                    </svg>
                </a>
            </div>

            <a href="{{ route('tournaments.archive') }}"
                class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-gray-700 text-gray-300 transition hover:border-amber-500 hover:text-amber-400"
                title="Archiv" aria-label="Archiv">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 7h18"></path>
                    <path d="M5 7l1 12h12l1-12"></path>
                    <path d="M9 11h6"></path>
                    <path d="M8 4h8l1 3H7l1-3z"></path>
                </svg>
            </a>

            {{-- Aktive Turniere --}}
            @foreach ($activeTournaments ?? [] as $tournament)
                <a href="{{ route('tournaments.show', $tournament) }}"
                    class="text-gray-400 hover:text-emerald-400 text-sm transition">

                    {{ $tournament->name }}

                </a>
            @endforeach

        </div>

        {{-- Rechte Seite --}}
        <div class="flex items-center gap-4">

            @auth
                <span class="text-gray-400 text-sm">
                    {{ auth()->user()->name }}
                </span>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-gray-400 hover:text-red-400 text-sm transition">
                        Logout
                    </button>
                </form>
            @endauth

        </div>

    </div>
</nav>
