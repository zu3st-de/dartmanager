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
                    {{-- Einzelner Turnier-Eintrag --}}
                    <div class="border-b border-gray-700 py-3">

                        {{-- Flexbox für Name links, Status rechts --}}
                        <div class="flex justify-between">

                            {{-- Turniername mit Link zur Detailseite --}}
                            <span class="text-lg text-white">
                                <a href="{{ route('tournaments.show', $tournament) }}"
                                    class="text-emerald-400 hover:underline">
                                    {{ $tournament->name }}
                                </a>
                            </span>

                            {{-- Status des Turniers (z.B. draft, running, finished) --}}
                            <span class="text-sm text-gray-400">
                                {{ ucfirst($tournament->status) }}
                            </span>

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
