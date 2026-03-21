<x-app-layout>

    <h1 class="text-xl font-bold mb-4">📦 Archivierte Turniere</h1>

    @if ($tournaments->isEmpty())
        <p class="text-gray-500">Keine archivierten Turniere vorhanden.</p>
    @endif

    <div class="space-y-3">

        @foreach ($tournaments as $tournament)
            <div class="flex items-center justify-between p-4 bg-gray-800 rounded">

                <div>
                    <div class="font-semibold">{{ $tournament->name }}</div>
                    <div class="text-xs text-gray-400">
                        {{ $tournament->created_at->format('d.m.Y') }}
                    </div>
                </div>

                <div class="flex gap-2">

                    <a href="{{ route('tournaments.show', $tournament) }}" class="text-blue-400 text-sm">
                        Öffnen
                    </a>

                    <form method="POST" action="{{ route('tournaments.restore', $tournament) }}">
                        @csrf
                        <button class="text-green-400 text-sm">
                            Wiederherstellen
                        </button>
                    </form>

                </div>

            </div>
        @endforeach

    </div>

</x-app-layout>
