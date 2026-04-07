<x-app-layout>

    {{-- Seiten-Header --}}
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Meine Turniere
        </h2>
    </x-slot>

    {{-- Hauptbereich --}}
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Button zum Erstellen eines neuen Turniers --}}
            <div class="mb-6">
                <a href="{{ route('tournaments.create') }}"
                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg">
                    + Neues Turnier
                </a>
            </div>

            {{-- Container für die Turnierliste --}}
            <div class="bg-gray-900 dark:bg-gray-800 shadow rounded-xl p-6">

                {{-- Schleife über alle Turniere --}}
                @forelse($tournaments as $tournament)
                    @php
                        $isOnTv = in_array($tournament->id, $tvTournamentIds ?? [], true);
                        $statusConfig = match ($tournament->status) {
                            'draft' => [
                                'label' => 'Entwurf',
                                'classes' => 'border-slate-600/80 bg-slate-500/10 text-slate-300',
                                'svg' => '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20h4l10-10-4-4L4 16v4z"></path><path d="M13 7l4 4"></path></svg>',
                            ],
                            'group_running' => [
                                'label' => 'Gruppenphase',
                                'classes' => 'border-cyan-500/60 bg-cyan-500/10 text-cyan-300',
                                'svg' => '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="7" cy="7" r="2"></circle><circle cx="17" cy="7" r="2"></circle><circle cx="12" cy="17" r="2"></circle><path d="M8.8 8.2l2.4 6.6"></path><path d="M15.2 8.2l-2.4 6.6"></path><path d="M9 7h6"></path></svg>',
                            ],
                            'ko_running' => [
                                'label' => 'KO-Phase',
                                'classes' => 'border-amber-500/60 bg-amber-500/10 text-amber-300',
                                'svg' => '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 4v6a3 3 0 003 3h6a3 3 0 003-3V4"></path><path d="M9 20h6"></path><path d="M12 14v6"></path><path d="M4 6h2"></path><path d="M18 6h2"></path></svg>',
                            ],
                            'finished' => [
                                'label' => 'Abgeschlossen',
                                'classes' => 'border-emerald-500/60 bg-emerald-500/10 text-emerald-300',
                                'svg' => '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 21h8"></path><path d="M12 17v4"></path><path d="M7 4h10v4a5 5 0 01-10 0V4z"></path><path d="M7 6H5a2 2 0 002 2"></path><path d="M17 6h2a2 2 0 01-2 2"></path></svg>',
                            ],
                            default => [
                                'label' => ucfirst((string) $tournament->status),
                                'classes' => 'border-gray-700 bg-gray-800 text-gray-300',
                                'svg' => '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="8"></circle></svg>',
                            ],
                        };
                    @endphp

                    {{-- Einzelner Turnier-Eintrag --}}
                    <div class="border-b border-gray-700 py-3">

                        {{-- Flexbox für Name links, Status rechts --}}
                        <div class="flex items-center justify-between gap-4">

                            {{-- Turniername mit Link zur Detailseite --}}
                            <span class="text-lg text-white">
                                <a href="{{ route('tournaments.show', $tournament) }}"
                                    class="text-emerald-400 hover:underline">
                                    {{ $tournament->name }}
                                </a>
                            </span>

                            <div class="flex items-center gap-2">
                                <span
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-full border {{ $statusConfig['classes'] }}"
                                    title="{{ $statusConfig['label'] }}" aria-label="{{ $statusConfig['label'] }}">
                                    {!! $statusConfig['svg'] !!}
                                </span>

                                <form method="POST" action="{{ route('tv.toggle', $tournament) }}">
                                    @csrf
                                    <button type="submit"
                                        class="inline-flex h-10 w-10 items-center justify-center rounded-full border transition {{ $isOnTv
                                            ? 'border-emerald-500 bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500/20'
                                            : 'border-gray-700 text-gray-400 hover:border-emerald-500 hover:text-emerald-400' }}"
                                        title="{{ $isOnTv ? 'Aus TV entfernen' : 'Zum TV hinzufügen' }}"
                                        aria-label="{{ $isOnTv ? 'Aus TV entfernen' : 'Zum TV hinzufügen' }}">
                                        <span class="relative inline-flex">
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                                                aria-hidden="true">
                                                <rect x="3" y="5" width="18" height="12" rx="2"></rect>
                                                <path d="M8 21h8"></path>
                                                <path d="M12 17v4"></path>
                                            </svg>
                                            @if ($isOnTv)
                                                <svg class="absolute -right-2 -top-2 h-4 w-4 rounded-full bg-gray-900 text-emerald-400"
                                                    viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                    <path fill-rule="evenodd"
                                                        d="M16.704 5.29a1 1 0 010 1.42l-7.2 7.2a1 1 0 01-1.414 0l-3-3a1 1 0 111.414-1.42l2.293 2.294 6.493-6.494a1 1 0 011.414 0z"
                                                        clip-rule="evenodd" />
                                                </svg>
                                            @endif
                                        </span>
                                    </button>
                                </form>

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

                                @if ($tournament->status !== 'archived')
                                    <form method="POST" action="{{ route('tournaments.archive.store', $tournament) }}">
                                        @csrf
                                        <button type="submit"
                                            class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-gray-700 text-gray-400 transition hover:border-amber-500 hover:text-amber-400"
                                            title="Archivieren" aria-label="Archivieren">
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                                                aria-hidden="true">
                                                <path d="M3 7h18"></path>
                                                <path d="M5 7l1 12h12l1-12"></path>
                                                <path d="M9 11h6"></path>
                                                <path d="M8 4h8l1 3H7l1-3z"></path>
                                            </svg>
                                        </button>
                                    </form>
                                @endif
                            </div>

                        </div>
                    </div>

                {{-- Falls keine Turniere vorhanden sind --}}
                @empty
                    <p class="text-gray-400">
                        Noch keine Turniere vorhanden.
                    </p>
                @endforelse

            </div>

        </div>
    </div>

</x-app-layout>
