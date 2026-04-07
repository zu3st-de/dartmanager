<x-app-layout>

    <div class="max-w-4xl mx-auto py-10 space-y-6">

        <div>
            <h1 class="text-2xl font-bold">
                TV Programm
            </h1>
            <p class="mt-2 text-sm text-gray-400">
                Aktiviere Turniere fuer die Rotation und ziehe sie in die gewuenschte Reihenfolge.
                Lucky-Loser-Turniere folgen in der Rotation automatisch direkt ihrem Hauptturnier.
            </p>
        </div>

        <form method="POST" action="{{ route('tv.save') }}" id="tv-program-form" class="space-y-6">
            @csrf

            <div class="rounded-2xl border border-gray-800 bg-gray-900 p-5 shadow-lg">
                <div class="mb-4 flex items-center justify-between gap-4">
                    <h2 class="text-lg font-semibold text-white">
                        Turniere
                    </h2>
                    <span class="text-xs text-gray-400">
                        Drag and drop zum Sortieren
                    </span>
                </div>

                <div id="tv-sortable-list" class="space-y-3">
                    @foreach ($orderedTournaments as $tournament)
                        @php
                            $isSelected = in_array($tournament->id, $selected);
                            $isLuckyLoser = $tournament->parent_id !== null;
                            $parentTournament = $isLuckyLoser ? $orderedTournaments->firstWhere('id', $tournament->parent_id) : null;
                        @endphp

                        <div class="tv-sort-item rounded-xl border border-gray-800 bg-gray-950/60 px-4 py-3 transition"
                            data-id="{{ $tournament->id }}" draggable="true">
                            <input type="hidden" name="ordered_tournaments[]" value="{{ $tournament->id }}">

                            <div class="flex items-center gap-4">
                                <div class="cursor-move select-none text-gray-500" aria-hidden="true">::</div>

                                <label class="flex flex-1 items-center gap-3">
                                    <input type="checkbox" name="tournaments[]" value="{{ $tournament->id }}"
                                        {{ $isSelected ? 'checked' : '' }}
                                        class="rounded border-gray-700 bg-gray-900 text-emerald-500 focus:ring-emerald-500">

                                    <div class="flex flex-col">
                                        <span class="text-white">
                                            {{ $tournament->name }}
                                        </span>

                                        <span class="text-xs text-gray-400">
                                            @if ($isLuckyLoser)
                                                Lucky Loser
                                                @if ($parentTournament)
                                                    von {{ $parentTournament->name }}
                                                @endif
                                            @else
                                                Hauptturnier
                                            @endif
                                        </span>
                                    </div>
                                </label>

                                @if ($isLuckyLoser)
                                    <span class="rounded-full border border-amber-500/40 bg-amber-500/10 px-2 py-1 text-xs text-amber-300">
                                        Lucky Loser
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-2xl border border-gray-800 bg-gray-900 p-5 shadow-lg">
                <label class="block text-sm text-gray-300 mb-2">
                    Rotationszeit (Sekunden)
                </label>

                <input type="number" name="rotation_time" value="{{ $rotationTime ?? 20 }}"
                    class="bg-gray-800 text-white px-3 py-2 rounded w-32" min="3" max="45">
            </div>

            <button class="px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-500 transition">
                Speichern
            </button>
        </form>

    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const list = document.getElementById('tv-sortable-list');
                if (!list) return;

                let draggedItem = null;

                const items = () => Array.from(list.querySelectorAll('.tv-sort-item'));

                function refreshOrderInputs() {
                    items().forEach(item => {
                        const hiddenInput = item.querySelector('input[name="ordered_tournaments[]"]');
                        if (hiddenInput) {
                            hiddenInput.value = item.dataset.id;
                        }
                    });
                }

                function getDragAfterElement(container, y) {
                    const draggableElements = [...container.querySelectorAll('.tv-sort-item:not(.dragging)')];

                    return draggableElements.reduce((closest, child) => {
                        const box = child.getBoundingClientRect();
                        const offset = y - box.top - box.height / 2;

                        if (offset < 0 && offset > closest.offset) {
                            return {
                                offset,
                                element: child
                            };
                        }

                        return closest;
                    }, {
                        offset: Number.NEGATIVE_INFINITY,
                        element: null
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
                    });
                });

                list.addEventListener('dragover', event => {
                    event.preventDefault();

                    if (!draggedItem) return;

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
