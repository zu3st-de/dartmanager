<x-app-layout>

    <div class="max-w-3xl mx-auto py-10">

        <h1 class="text-2xl font-bold mb-6">
            TV Programm
        </h1>

        <form method="POST">

            @csrf

            <div class="space-y-3">

                @foreach($tournaments as $tournament)

                <label class="flex items-center gap-3">

                    <input type="checkbox"
                        name="tournaments[]"
                        value="{{ $tournament->id }}"
                        {{ in_array($tournament->id,$selected) ? 'checked' : '' }}>

                    <span>
                        {{ $tournament->name }}
                    </span>

                </label>

                @endforeach

            </div>

            <button class="mt-6 px-4 py-2 bg-blue-600 text-white rounded">
                Speichern
            </button>

        </form>

    </div>

</x-app-layout>