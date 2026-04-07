<nav class="bg-gray-900 border-b border-gray-800 px-6 py-3">
    <div class="flex items-center justify-between">

        <div class="flex items-center gap-8">
            <a href="{{ route('tournaments.index') }}" class="flex items-center gap-3">
                <img src="{{ asset('images/dart-manager-horizontal-normal.png') }}"
                    srcset="{{ asset('images/dart-manager-horizontal@2x.png') }} 2x" height="50"
                    alt="Dart Manager Logo">
            </a>

            <a href="{{ route('tournaments.index') }}"
                class="text-gray-300 hover:text-white text-sm font-semibold transition">
                Alle Turniere
            </a>

            <div class="relative"
                x-data="{
                    open: false,
                    rotation: {{ $tvRotationTime ?? 20 }},
                    saveTimer: null,
                    saving: false,
                    saveState: '',
                    closeTimer: null,
                    show() {
                        clearTimeout(this.closeTimer);
                        this.open = true;
                    },
                    hide() {
                        this.closeTimer = setTimeout(() => {
                            this.open = false;
                        }, 180);
                    },
                    queueSave() {
                        if (!{{ ($hasTvTournaments ?? false) ? 'true' : 'false' }}) {
                            return;
                        }

                        clearTimeout(this.saveTimer);
                        this.saveTimer = setTimeout(() => this.save(), 250);
                    },
                    async save() {
                        this.saving = true;
                        this.saveState = '';

                        try {
                            const response = await fetch('{{ route('tv.rotation-time') }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                },
                                body: JSON.stringify({
                                    rotation_time: this.rotation
                                })
                            });

                            const data = await response.json();

                            if (!response.ok) {
                                throw new Error(data.message || 'Speichern fehlgeschlagen.');
                            }

                            this.saveState = 'Gespeichert';
                        } catch (error) {
                            this.saveState = error.message || 'Speichern fehlgeschlagen.';
                        } finally {
                            this.saving = false;
                        }
                    }
                }">
                <div x-cloak x-show="open" x-transition.opacity.duration.150ms
                    @mouseenter="show()" @mouseleave="hide()"
                    class="absolute left-1/2 top-full z-20 mt-3 w-64 -translate-x-1/2 rounded-2xl border border-gray-700 bg-gray-900/95 p-4 shadow-2xl backdrop-blur">
                    <form class="space-y-3" @submit.prevent>
                        @csrf
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-sm font-medium text-white">Rotation</span>
                            <span class="text-sm text-emerald-400" x-text="rotation + 's'"></span>
                        </div>

                        <input type="range" name="rotation_time" min="3" max="45" step="1"
                            x-model="rotation" @input="queueSave()"
                            class="h-2 w-full cursor-pointer appearance-none rounded-full bg-gray-700 accent-emerald-500"
                            {{ ($hasTvTournaments ?? false) ? '' : 'disabled' }}>

                        <div class="flex items-center justify-between gap-3 min-h-[1.25rem]">
                            <span class="text-xs text-gray-400">
                                @if ($hasTvTournaments ?? false)
                                    <span x-show="saving">Speichert...</span>
                                    <span x-show="!saving && !saveState">Für alle TV-Turniere</span>
                                    <span x-show="!saving && saveState" x-text="saveState"></span>
                                @else
                                    Erst ein Turnier fürs TV markieren
                                @endif
                            </span>
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

            @foreach ($activeTournaments ?? [] as $tournament)
                <a href="{{ route('tournaments.show', $tournament) }}"
                    class="text-gray-400 hover:text-emerald-400 text-sm transition">
                    {{ $tournament->name }}
                </a>
            @endforeach
        </div>

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
