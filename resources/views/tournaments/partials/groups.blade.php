@if($tournament->status === 'group_running' || $tournament->status === 'ko_running')

<div class="bg-white shadow rounded p-6 mb-6">

    <h2 class="text-lg font-semibold mb-6">
        Gruppenphase
    </h2>


    @foreach($tournament->groups as $group)

    <div class="mb-8">

        {{-- Gruppenname --}}
        <h3 class="font-semibold text-md mb-3">
            Gruppe {{ $group->name }}
        </h3>


        {{-- Tabelle --}}
        @php
        $table = app(\App\Services\GroupTableCalculator::class)
        ->calculate($group);
        @endphp

        <table class="w-full border mb-4">

            <thead class="bg-gray-100">

                <tr>
                    <th class="text-left p-2">#</th>
                    <th class="text-left p-2">Spieler</th>
                    <th class="text-left p-2">Punkte</th>
                    <th class="text-left p-2">Diff</th>
                </tr>

            </thead>

            <tbody>

                @foreach($table as $index => $row)

                <tr class="border-t">

                    <td class="p-2">
                        {{ $index + 1 }}
                    </td>

                    <td class="p-2">
                        {{ $row['player']->name }}
                    </td>

                    <td class="p-2">
                        {{ $row['points'] }}
                    </td>

                    <td class="p-2">
                        {{ $row['difference'] }}
                    </td>

                </tr>

                @endforeach

            </tbody>

        </table>


        {{-- Spiele --}}
        <div>

            <h4 class="font-medium mb-2">
                Spiele
            </h4>

            @foreach($group->games as $game)

            <div class="flex justify-between items-center border rounded px-3 py-2 mb-2">

                <div>

                    {{ $game->player1->name ?? '?' }}
                    vs
                    {{ $game->player2->name ?? '?' }}

                </div>


                {{-- Ergebnis --}}
                <div>

                    @if($game->winner_id)

                    {{ $game->player1_score }}
                    :
                    {{ $game->player2_score }}

                    @else

                    <div class="score-form"
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

                    </div>

                    @endif

                </div>

            </div>

            @endforeach

        </div>

    </div>

    @endforeach
    @if($tournament->status === 'group_running')

    <form method="POST"
        action="{{ route('tournaments.finishGroups', $tournament) }}"
        class="mt-6">

        @csrf

        <button
            class="bg-green-600 text-white px-4 py-2 rounded">

            Gruppenphase abschließen & KO starten

        </button>

    </form>

    @endif

</div>
@push('scripts')
<script>
    document.addEventListener('keydown', async function(e) {

        if (e.key !== 'Enter') return;

        const input = e.target;

        if (!input.classList.contains('score-input')) return;

        e.preventDefault();

        const form = input.closest('.score-form');
        if (!form) return;

        const inputs = form.querySelectorAll('.score-input');

        if (!inputs[0].value || !inputs[1].value) return;

        const formData = new FormData();
        formData.append('_token',
            form.querySelector('.csrf-token').value);
        formData.append('player1_score', inputs[0].value);
        formData.append('player2_score', inputs[1].value);

        await fetch(form.dataset.url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        });

        const forms =
            Array.from(document.querySelectorAll('.score-form'));

        const index = forms.indexOf(form);
        const next = forms[index + 1];

        if (next) {
            next.querySelector('.score-input').focus();
        }

    });
</script>
@endpush

@endif