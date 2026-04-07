<x-app-layout>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="mb-6 flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-white">Archivierte Turniere</h1>
                    <p class="mt-1 text-sm text-gray-400">Abgelegte Turniere können hier wiederhergestellt werden.</p>
                </div>

                <a href="{{ route('tournaments.index') }}"
                    class="inline-flex items-center rounded-full border border-gray-700 px-4 py-2 text-sm text-gray-300 transition hover:border-emerald-500 hover:text-emerald-400">
                    Zur Übersicht
                </a>
            </div>

            @if ($tournaments->isEmpty())
                <div class="rounded-xl border border-dashed border-gray-700 bg-gray-900/70 p-8 text-center">
                    <p class="text-gray-400">Keine archivierten Turniere vorhanden.</p>
                </div>
            @else
                <div class="rounded-xl bg-gray-900 p-6 shadow">
                    <div class="space-y-3">
                        @foreach ($tournaments as $tournament)
                            <div class="rounded-xl border border-gray-800 bg-gray-800/80 p-4">
                                <div class="flex items-center justify-between gap-4">
                                    <div>
                                        <div class="font-semibold text-white">{{ $tournament->name }}</div>
                                        <div class="mt-1 text-xs text-gray-400">
                                            Archiviert am {{ $tournament->updated_at?->format('d.m.Y') ?? $tournament->created_at->format('d.m.Y') }}
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('tournament.follow', $tournament) }}" target="_blank" rel="noopener"
                                            class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-gray-700 text-gray-300 transition hover:border-sky-500 hover:text-sky-400"
                                            title="Follow-Seite in neuem Fenster öffnen"
                                            aria-label="Follow-Seite in neuem Fenster öffnen">
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="M4 4h6v6H4z"></path>
                                                <path d="M14 4h6v6h-6z"></path>
                                                <path d="M4 14h6v6H4z"></path>
                                                <path d="M14 14h2"></path>
                                                <path d="M18 14h2v2"></path>
                                                <path d="M14 18h2v2h-2z"></path>
                                                <path d="M18 18h2v2h-2z"></path>
                                                <path d="M16 16h2"></path>
                                            </svg>
                                        </a>

                                        <form method="POST" action="{{ route('tournaments.restore', $tournament) }}">
                                            @csrf
                                            <button type="submit"
                                                class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-gray-700 text-gray-400 transition hover:border-emerald-500 hover:text-emerald-400"
                                                title="Wiederherstellen" aria-label="Wiederherstellen">
                                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                                                    aria-hidden="true">
                                                    <path d="M3 12a9 9 0 109-9"></path>
                                                    <path d="M3 3v6h6"></path>
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

</x-app-layout>
