<div class="bg-white shadow rounded p-6 mb-6">

    <h2 class="text-lg font-semibold mb-4">
        Turnieraktionen
    </h2>


    {{-- Turnier starten --}}
    @if($tournament->status === 'draft')

    <form method="POST"
        action="{{ route('tournaments.start', $tournament) }}"
        id="start-form"
        class="{{ $tournament->players->count() < 2 ? 'hidden' : '' }}">

        @csrf

        <button type="submit"
            class="bg-blue-600 text-white px-4 py-2 rounded">
            Turnier starten
        </button>

    </form>

    @endif


    {{-- KO Phase starten --}}
    @if($tournament->status === 'group_running')

    <form method="POST"
        action="{{ route('tournaments.startKo', $tournament) }}">

        @csrf

        <button
            type="submit"
            class="bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700">

            KO-Phase starten

        </button>

    </form>

    @endif

</div>
@push('scripts')
<script>
    document.addEventListener('player-added', function() {

        const startForm = document.getElementById('start-form');
        if (!startForm) return;

        const playerRows =
            document.querySelectorAll('.player-row');

        if (playerRows.length >= 2) {
            startForm.classList.remove('hidden');
        }

    });
</script>
@endpush