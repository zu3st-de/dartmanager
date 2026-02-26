<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-200">
            Neues Turnier erstellen
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto">

            <div class="bg-gray-900 shadow rounded-xl p-6">
                <form method="POST" action="{{ route('tournaments.store') }}">
                    @csrf

                    {{-- Fehleranzeige --}}
                    @if ($errors->any())
                        <div class="mb-4 bg-red-600 text-white p-3 rounded-lg">
                            <ul class="text-sm space-y-1">
                                @foreach ($errors->all() as $error)
                                    <li>• {{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Turniername --}}
                    <div class="mb-4">
                        <label class="block text-gray-300 mb-2">Turniername</label>
                        <input type="text" name="name" value="{{ old('name') }}"
                            class="w-full rounded-lg bg-gray-700 text-white border-gray-600 focus:ring-emerald-500">
                    </div>

                    {{-- Modus --}}
                    <div class="mb-4">
                        <label class="block text-gray-300 mb-2">Spielmodus</label>
                        <select name="mode"
                            class="w-full rounded-lg bg-gray-700 text-white border-gray-600 focus:ring-emerald-500">
                            <option value="ko" {{ old('mode') == 'ko' ? 'selected' : '' }}>KO-System</option>
                            <option value="group_ko" {{ old('mode') == 'group_ko' ? 'selected' : '' }}>Gruppenphase + KO
                            </option>
                        </select>
                    </div>

                    {{-- Gruppen Einstellungen --}}
                    <div id="group-settings" class="space-y-4 hidden">

                        <div>
                            <label class="block text-sm text-gray-300">
                                Anzahl Gruppen
                            </label>
                            <input type="number" name="group_count" min="1" value="{{ old('group_count') }}"
                                class="w-full bg-gray-700 text-white rounded px-3 py-2">
                        </div>

                        <div>
                            <label class="block text-sm text-gray-300">
                                Weiter pro Gruppe
                            </label>
                            <input type="number" name="group_advance_count" min="1"
                                value="{{ old('group_advance_count') }}"
                                class="w-full bg-gray-700 text-white rounded px-3 py-2">
                        </div>

                    </div>

                    {{-- Optionen --}}
                    <div class="mb-4 flex items-center space-x-6">
                        <label class="flex items-center text-gray-300">
                            <input type="checkbox" name="has_lucky_loser" class="mr-2"
                                {{ old('has_lucky_loser') ? 'checked' : '' }}>
                            Lucky Loser
                        </label>

                        <label class="flex items-center text-gray-300">
                            <input type="checkbox" name="has_third_place" class="mr-2"
                                {{ old('has_third_place') ? 'checked' : '' }}>
                            Spiel um Platz 3
                        </label>
                    </div>

                    <div class="mt-6">
                        <button type="submit"
                            class="px-6 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg">
                            Turnier erstellen
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>

    {{-- Script --}}
    <script>
        const modeSelect = document.querySelector('select[name="mode"]');
        const groupSettings = document.getElementById('group-settings');
        const groupInputs = groupSettings.querySelectorAll('input');

        function toggleGroupSettings() {
            if (modeSelect.value === 'group_ko') {
                groupSettings.classList.remove('hidden');
                groupInputs.forEach(input => input.required = true);
            } else {
                groupSettings.classList.add('hidden');
                groupInputs.forEach(input => input.required = false);
            }
        }

        modeSelect.addEventListener('change', toggleGroupSettings);
        toggleGroupSettings();
    </script>

</x-app-layout>
