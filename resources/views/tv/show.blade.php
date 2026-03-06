@extends('layouts.tv')

@section('content')

<div class="mb-10 text-center">

    <h1 class="text-4xl font-bold text-white">
        {{ $tournament->name }}
    </h1>

</div>

<div class="flex justify-center">

    <div class="flex flex-wrap justify-center gap-10">

        @foreach($groupData as $data)

        <div class="bg-gray-900 border border-gray-800 rounded-xl p-6 shadow-lg w-[420px]">

            <div class="text-xl font-semibold text-white mb-6">
                Gruppe {{ $data['group']->name }}
            </div>


            {{-- TABELLE --}}
            <div class="overflow-hidden rounded-lg border border-gray-700">

                <table class="w-full text-base text-gray-300">

                    <thead class="bg-gray-800 text-gray-400 uppercase text-xs tracking-wider">

                        <tr>
                            <th class="px-2 py-2 text-left w-6">#</th>
                            <th class="px-2 py-2 text-left">Spieler</th>
                            <th class="px-2 py-2 text-center w-8">Sp</th>
                            <th class="px-2 py-2 text-center w-8">S</th>
                            <th class="px-2 py-2 text-center w-8">N</th>
                            <th class="px-2 py-2 text-center w-10">Diff</th>
                            <th class="px-2 py-2 text-center w-10">Pkt</th>
                        </tr>

                    </thead>

                    <tbody>

                        @foreach($data['table'] as $index => $row)

                        @php
                        $isQualified = $index < $tournament->group_advance_count;
                            $isFirst = $index === 0;
                            $diff = $row['difference'];
                            @endphp

                            <tr class="
                {{ $isQualified ? 'bg-green-600/10' : 'bg-gray-900' }}
                border-t border-gray-800
            ">

                                {{-- Platz --}}
                                <td class="px-2 py-2 font-mono
                    {{ $isFirst ? 'text-yellow-400 font-bold' : '' }}">
                                    {{ $index + 1 }}
                                </td>


                                {{-- Spieler --}}
                                <td class="px-2 py-2
                    {{ $isQualified ? 'text-green-400' : '' }}">

                                    @if($isFirst)
                                    🏆
                                    @endif

                                    {{ $row['player']->name }}

                                </td>


                                <td class="px-2 py-2 text-center">
                                    {{ $row['played'] }}
                                </td>

                                <td class="px-2 py-2 text-center text-green-400">
                                    {{ $row['wins'] }}
                                </td>

                                <td class="px-2 py-2 text-center text-red-400">
                                    {{ $row['losses'] }}
                                </td>


                                {{-- Diff --}}
                                <td class="px-2 py-2 text-center font-mono
                    {{ $diff > 0 ? 'text-green-400' : ($diff < 0 ? 'text-red-400' : 'text-gray-400') }}">
                                    {{ $diff > 0 ? '+' : '' }}{{ $diff }}
                                </td>


                                {{-- Punkte --}}
                                <td class="px-2 py-2 text-center font-bold text-white">
                                    {{ $row['points'] }}
                                </td>

                            </tr>

                            @endforeach

                    </tbody>

                </table>

            </div>


            {{-- LETZTES SPIEL --}}
            @if($data['lastGame'])

            @php
            $game = $data['lastGame'];

            $p1Winner = $game->winner_id === $game->player1_id;
            $p2Winner = $game->winner_id === $game->player2_id;
            @endphp

            <div class="mt-6 text-xs text-gray-400 uppercase tracking-wider">
                Letztes Spiel
            </div>

            <div class="flex justify-between text-lg mb-4">

                <span class="{{ $p1Winner ? 'text-green-400 font-semibold' : '' }}">
                    {{ $game->player1->name }}
                </span>

                <span class="font-mono">
                    {{ $game->player1_score }}
                    -
                    {{ $game->player2_score }}
                </span>

                <span class="{{ $p2Winner ? 'text-green-400 font-semibold' : '' }}">
                    {{ $game->player2->name }}
                </span>

            </div>

            @endif


            {{-- JETZT AM BOARD --}}
            @if($data['currentGame'])

            <div class="text-xs text-yellow-400 uppercase tracking-wider">
                Jetzt am Board
            </div>

            <div class="flex justify-between text-xl font-semibold text-yellow-300 mb-4">

                <span>
                    {{ $data['currentGame']->player1->name ?? 'TBD' }}
                </span>

                <span class="text-gray-400">
                    vs
                </span>

                <span>
                    {{ $data['currentGame']->player2->name ?? 'TBD' }}
                </span>

            </div>

            @endif


            {{-- NÄCHSTES SPIEL --}}
            @if($data['nextGame'])

            <div class="text-xs text-gray-400 uppercase tracking-wider">
                Nächstes Spiel
            </div>

            <div class="flex justify-between text-lg">

                <span>
                    {{ $data['nextGame']->player1->name ?? 'TBD' }}
                </span>

                <span class="text-gray-500">
                    vs
                </span>

                <span>
                    {{ $data['nextGame']->player2->name ?? 'TBD' }}
                </span>

            </div>

            @endif


        </div>

        @endforeach

    </div>

</div>

@endsection