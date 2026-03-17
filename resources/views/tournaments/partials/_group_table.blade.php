@php
    $table = app(\App\Services\GroupTableCalculator::class)->calculate($group);
@endphp

<table class="w-full text-xs text-gray-300 border border-gray-700">

    <thead class="bg-gray-800 text-gray-400">
        <tr>
            <th>#</th>
            <th>Spieler</th>
            <th>Sp</th>
            <th>S</th>
            <th>N</th>
            <th>Diff</th>
            <th>Pkt</th>
        </tr>
    </thead>

    <tbody>
        @foreach ($table as $index => $row)
            @php
                $qualified = $index < $tournament->group_advance_count;
            @endphp

            <tr class="{{ $qualified ? 'bg-green-600/10' : '' }}">

                <td>{{ $index + 1 }}</td>

                <td class="{{ $qualified ? 'text-green-400' : '' }}">
                    {{ $row['player']->name }}
                </td>

                <td>{{ $row['played'] }}</td>
                <td class="text-green-400">{{ $row['wins'] }}</td>
                <td class="text-red-400">{{ $row['losses'] }}</td>

                <td class="font-mono">
                    {{ $row['difference'] }}
                </td>

                <td class="font-bold">
                    {{ $row['points'] }}
                </td>

            </tr>
        @endforeach
    </tbody>

</table>
