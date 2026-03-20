<x-app-layout>

    <div class="max-w-3xl mx-auto py-10">

        <h1 class="text-2xl font-bold mb-6">
            TV Programm
        </h1>

        <form method="POST">

            @csrf

            <div class="space-y-3">

                @foreach ($tournaments as $tournament)
                    <label class="flex items-center gap-3">

                        <input type="checkbox" name="tournaments[]" value="{{ $tournament->id }}"
                            {{ in_array($tournament->id, $selected) ? 'checked' : '' }}>

                        <span>
                            {{ $tournament->name }}
                        </span>

                    </label>
                @endforeach

            </div>
            <div class="mb-6">
                <label class="block text-sm text-gray-300 mb-2">
                    Rotationszeit (Sekunden)
                </label>

                <input type="number" name="rotation_time" value="{{ $rotationTime ?? 20 }}"
                    class="bg-gray-800 text-white px-3 py-2 rounded w-32" min="5" max="300">
            </div>
            <button class="mt-6 px-4 py-2 bg-blue-600 text-white rounded">
                Speichern
            </button>

        </form>

    </div>

</x-app-layout>
