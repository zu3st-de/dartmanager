@if($tournament->status === 'ko_running' || $tournament->status === 'finished')

<div class="bg-white shadow rounded p-6 mb-6">

    <h2 class="text-lg font-semibold mb-6">
        KO-Phase
    </h2>
    @if($tournament->status === 'ko_running')

    <div class="mb-4">
        <button id="save-all-btn"
            class="bg-green-600 text-white px-4 py-2 rounded">
            Alle offenen Spiele speichern
        </button>
    </div>

    @endif
    @php
    $rounds = $tournament->games
    ->where('group_id', null)
    ->groupBy('round')
    ->sortKeys();

    function roundNameFromGameCount($gameCount)
    {
    return match($gameCount) {
    1 => 'Finale',
    2 => 'Halbfinale',
    4 => 'Viertelfinale',
    default => 'Runde'
    };
    }
    @endphp

    <div class="flex gap-6">

        @foreach($rounds as $round => $games)

        <div>

            @php
            $koGames = $tournament->games
            ->where('group_id', null);

            $koPlayerCount = $koGames
            ->where('round', 1)
            ->flatMap(fn($g) => [$g->player1_id, $g->player2_id])
            ->unique()
            ->count();

            $totalRounds = $koPlayerCount > 0
            ? log($koPlayerCount, 2)
            : 0;
            @endphp

            <h3 class="font-semibold mb-2">

                @if($round > $totalRounds)
                Spiel um Platz 3
                @else
                {{ roundNameFromGameCount($games->count()) }}
                @endif

            </h3>

            @foreach($games->sortBy('position') as $game)

            <div class="match border p-2 mb-2 rounded transition"
                data-match>

                <div>
                    {{ $game->player1->name ?? 'TBD' }}
                </div>

                <div>
                    {{ $game->player2->name ?? 'TBD' }}
                </div>

                @if(!$game->winner_id)
                <div class="score-form flex items-center gap-2"
                    data-url="{{ route('games.updateScore', $game) }}">

                    <input type="hidden"
                        class="csrf-token"
                        value="{{ csrf_token() }}">

                    <input type="number"
                        class="score-input border w-12 text-center"
                        min="0"
                        required>

                    :

                    <input type="number"
                        class="score-input border w-12 text-center"
                        min="0"
                        required>

                    <button type="button"
                        class="save-btn bg-blue-600 text-white px-2 py-1 rounded text-sm">
                        OK
                    </button>

                </div>
                @else

                <div>
                    {{ $game->player1_score }}
                    :
                    {{ $game->player2_score }}
                </div>

                @endif

            </div>

            @endforeach

        </div>

        @endforeach

    </div>

</div>

@push('scripts')
<script>
    async function submitScore(form) {

        const inputs = form.querySelectorAll('.score-input');

        if (!inputs[0].value || !inputs[1].value) return false;

        const formData = new FormData();

        formData.append('_token',
            form.querySelector('.csrf-token').value);

        formData.append('player1_score', inputs[0].value);
        formData.append('player2_score', inputs[1].value);

        const response = await fetch(form.dataset.url, {

            method: 'POST',

            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },

            body: formData

        });

        if (!response.ok) {
            console.error("Speichern fehlgeschlagen");
            return false;
        }

        // visuelles Feedback
        const match = form.closest('.match');
        match.classList.remove('border');
        match.classList.add('border-green-500', 'bg-green-50');

        return true;
    }



    function focusNext(form) {

        const forms =
            Array.from(document.querySelectorAll('.score-form'));

        const index = forms.indexOf(form);
        const next = forms[index + 1];

        if (next) {
            next.querySelector('.score-input').focus();
        }

    }


    // OK-Button
    document.addEventListener('click', async function(e) {

        if (!e.target.classList.contains('save-btn')) return;

        const form = e.target.closest('.score-form');

        const success = await submitScore(form);

        if (success) {
            location.reload();
        }

    });


    // Enter-Handling
    document.addEventListener('keydown', async function(e) {

        if (e.key !== 'Enter') return;

        const input = e.target;

        if (!input.classList.contains('score-input')) return;

        e.preventDefault();

        const form = input.closest('.score-form');

        const success = await submitScore(form);

        if (success) {
            location.reload();
        }

    });


    // ALLE SPIELE SPEICHERN
    document.getElementById('save-all-btn')?.addEventListener('click', async function() {

        const forms = document.querySelectorAll('.score-form');

        for (const form of forms) {

            await submitScore(form);

        }

        // prüfen ob Finale entschieden wurde
        const remaining =
            document.querySelectorAll('.score-form');

        if (remaining.length === 0) {

            location.reload();

        }

    });
</script>
@endpush

@endif