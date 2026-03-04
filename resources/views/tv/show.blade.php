@extends('layouts.tv')

@section('content')

<div class="mb-6 text-3xl font-bold">
    {{ $tournament->name }}
</div>

<div class="grid grid-cols-2 xl:grid-cols-4 gap-6">

    @foreach($groupData as $data)

    <div class="bg-gray-900 rounded-xl p-4">

        <div class="text-xl font-semibold mb-4">
            Gruppe {{ $data['group']->name }}
        </div>

        <table class="w-full text-sm mb-4">
            @foreach($data['table'] as $index => $row)
            <tr class="border-b border-gray-700">
                <td class="py-1 w-6">{{ $index + 1 }}</td>
                <td>{{ $row['player']->name }}</td>
                <td class="text-right">{{ $row['points'] }}</td>
            </tr>
            @endforeach
        </table>

    </div>

    @endforeach

</div>

@endsection