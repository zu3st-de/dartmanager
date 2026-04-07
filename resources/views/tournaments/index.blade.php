<x-app-layout>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Meine Turniere
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">

            <div class="mb-6 flex items-end justify-between gap-4">
                <a href="{{ route('tournaments.create') }}"
                    class="rounded-lg bg-emerald-600 px-4 py-2 text-white hover:bg-emerald-700">
                    + Neues Turnier
                </a>

                <form method="POST" action="{{ route('tv.save') }}" id="tv-order-form" class="hidden">
                    @csrf
                    <input type="hidden" name="rotation_time" value="{{ $tvRotationTime ?? 20 }}">

                    @foreach ($tvTournamentIds as $tournamentId)
                        <input type="hidden" name="tournaments[]" value="{{ $tournamentId }}">
                    @endforeach
                </form>
            </div>

            <div class="rounded-xl bg-gray-900 p-6 shadow dark:bg-gray-800">
                <div class="mb-4 flex items-center justify-between gap-4 text-sm text-gray-400">
                    <span>
                        Die vorhandene Turnierübersicht ist ziehbar. Die TV-Reihenfolge wird beim Loslassen direkt übernommen.
                        Lucky-Loser-Turniere starten beim Hinzufügen direkt hinter ihrem Hauptturnier und können danach frei verschoben werden.
                    </span>
                    <span id="tv-order-status" class="text-xs text-emerald-400"></span>
                </div>

                <div id="tournament-sortable-list">
                    @forelse($orderedTournaments as $tournament)
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

                        <div class="tournament-sort-item border-b border-gray-700 py-3"
                            data-id="{{ $tournament->id }}"
                            data-tv-selected="{{ $isOnTv ? '1' : '0' }}"
                            draggable="true">
                            <input type="hidden" name="ordered_tournaments[]" value="{{ $tournament->id }}"
                                form="tv-order-form">

                            <div class="flex items-center justify-between gap-4">
                                <div class="flex items-center gap-3">
                                    <span class="cursor-move select-none text-gray-500">::</span>

                                    <span class="text-lg text-white">
                                        <a href="{{ route('tournaments.show', $tournament) }}"
                                            class="text-emerald-400 hover:underline">
                                            {{ $tournament->name }}
                                        </a>
                                    </span>
                                </div>

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
                                            stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                                            aria-hidden="true">
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
                    @empty
                        <p class="text-gray-400">
                            Noch keine Turniere vorhanden.
                        </p>
                    @endforelse
                </div>
            </div>

        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const list = document.getElementById('tournament-sortable-list');
                const form = document.getElementById('tv-order-form');
                const status = document.getElementById('tv-order-status');

                if (!list || !form) {
                    return;
                }

                let draggedItem = null;
                let saveTimeout = null;
                let saveRequest = null;

                const items = () => Array.from(list.querySelectorAll('.tournament-sort-item'));
                const csrfToken = form.querySelector('input[name="_token"]')?.value;

                function refreshOrderInputs() {
                    items().forEach(item => {
                        const hiddenInput = item.querySelector('input[name="ordered_tournaments[]"]');

                        if (hiddenInput) {
                            hiddenInput.value = item.dataset.id;
                        }
                    });
                }

                function setStatus(message, isError = false) {
                    if (!status) {
                        return;
                    }

                    status.textContent = message;
                    status.classList.toggle('text-emerald-400', !isError);
                    status.classList.toggle('text-rose-400', isError);
                }

                function selectedTournamentIds() {
                    return items()
                        .filter(item => item.dataset.tvSelected === '1')
                        .map(item => Number(item.dataset.id));
                }

                function orderedTournamentIds() {
                    return items().map(item => Number(item.dataset.id));
                }

                async function persistTvOrder() {
                    if (!csrfToken) {
                        return;
                    }

                    if (saveRequest) {
                        saveRequest.abort();
                    }

                    saveRequest = new AbortController();
                    setStatus('TV-Reihenfolge wird gespeichert ...');

                    try {
                        const response = await fetch(form.action, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: JSON.stringify({
                                tournaments: selectedTournamentIds(),
                                ordered_tournaments: orderedTournamentIds(),
                                rotation_time: Number(form.querySelector('input[name="rotation_time"]')?.value || 20),
                            }),
                            signal: saveRequest.signal,
                        });

                        if (!response.ok) {
                            throw new Error('save_failed');
                        }

                        setStatus('TV-Reihenfolge gespeichert.');
                    } catch (error) {
                        if (error.name === 'AbortError') {
                            return;
                        }

                        setStatus('Speichern fehlgeschlagen. Bitte Seite neu laden.', true);
                    }
                }

                function queuePersistTvOrder() {
                    window.clearTimeout(saveTimeout);
                    saveTimeout = window.setTimeout(() => {
                        persistTvOrder();
                    }, 150);
                }

                function getDragAfterElement(container, y) {
                    const draggableElements = [...container.querySelectorAll('.tournament-sort-item:not(.dragging)')];

                    return draggableElements.reduce((closest, child) => {
                        const box = child.getBoundingClientRect();
                        const offset = y - box.top - box.height / 2;

                        if (offset < 0 && offset > closest.offset) {
                            return {
                                offset,
                                element: child,
                            };
                        }

                        return closest;
                    }, {
                        offset: Number.NEGATIVE_INFINITY,
                        element: null,
                    }).element;
                }

                items().forEach(item => {
                    item.addEventListener('dragstart', () => {
                        draggedItem = item;
                        item.classList.add('dragging', 'opacity-60');
                    });

                    item.addEventListener('dragend', () => {
                        item.classList.remove('dragging', 'opacity-60');
                        draggedItem = null;
                        refreshOrderInputs();
                        queuePersistTvOrder();
                    });
                });

                list.addEventListener('dragover', event => {
                    event.preventDefault();

                    if (!draggedItem) {
                        return;
                    }

                    const afterElement = getDragAfterElement(list, event.clientY);

                    if (!afterElement) {
                        list.appendChild(draggedItem);
                    } else {
                        list.insertBefore(draggedItem, afterElement);
                    }
                });

                refreshOrderInputs();
            });
        </script>
    @endpush

</x-app-layout>
