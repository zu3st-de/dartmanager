@if($tournament->status === 'group_running')

<div class="bg-gray-900 border border-gray-800 rounded-xl p-6 shadow-lg">

    <h2 class="text-lg font-semibold mb-6 text-white">
        Gruppenphase
    </h2>

    <div class="flex gap-8 flex-wrap">

        @foreach($tournament->groups as $group)

        @php
        $table = app(\App\Services\GroupTableCalculator::class)
        ->calculate($group);
        @endphp

        <div class="min-w-[320px]">

            <h3 class="text-sm text-gray-400 mb-4">
                Gruppe {{ $group->name }}
            </h3>

            {{-- 📊 TABELLE --}}
            <div class="mb-6 overflow-x-auto">
                <table class="w-full text-xs text-gray-300 border border-gray-700 rounded-lg overflow-hidden">

                    <thead class="bg-gray-800 text-gray-400 uppercase text-[10px] tracking-wider">
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

                        @foreach($table as $index => $row)

                        @php
                        $isQualified = $index < $tournament->group_advance_count;
                            $isFirst = $index === 0;
                            $diff = $row['difference'];
                            @endphp

                            <tr class="
                            {{ $isQualified ? 'bg-green-600/10' : 'bg-gray-900' }}
                            hover:bg-gray-800 transition
                        ">

                                {{-- Platz --}}
                                <td class="px-2 py-2 font-mono
                                {{ $isFirst ? 'text-yellow-400 font-bold' : '' }}">
                                    {{ $index + 1 }}
                                </td>

                                {{-- Spieler --}}
                                <td class="px-2 py-2
                                {{ $isQualified ? 'text-green-400' : '' }}">
                                    @if($isFirst) 🥇 @endif
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

                                {{-- Diff farbig --}}
                                <td class="px-2 py-2 text-center font-mono
                                {{ $diff > 0 ? 'text-green-400' : ($diff < 0 ? 'text-red-400' : 'text-gray-400') }}">
                                    {{ $diff > 0 ? '+' : '' }}{{ $diff }}
                                </td>

                                {{-- Punkte --}}
                                <td class="px-2 py-2 text-center font-bold
                                {{ $isQualified ? 'text-green-400' : '' }}">
                                    {{ $row['points'] }}
                                </td>

                            </tr>

                            @endforeach

                    </tbody>
                </table>
            </div>

            {{-- 🎯 GRUPPENSPIELE --}}
            @foreach($group->games as $game)

            <div class="bg-gray-800 border border-gray-700 rounded-lg p-4 shadow mb-4">

                @if(!$game->winner_id)

                <form method="POST"
                    action="{{ route('games.updateScore', $game) }}"
                    class="space-y-3">

                    @csrf

                    <div class="flex justify-between items-center text-sm">
                        <span>{{ $game->player1->name ?? 'TBD' }}</span>
                        <input type="number"
                            name="player1_score"
                            min="0"
                            required
                            class="w-12 bg-gray-900 border border-gray-700 rounded text-center text-white">
                    </div>

                    <div class="flex justify-between items-center text-sm">
                        <span>{{ $game->player2->name ?? 'TBD' }}</span>
                        <input type="number"
                            name="player2_score"
                            min="0"
                            required
                            class="w-12 bg-gray-900 border border-gray-700 rounded text-center text-white">
                    </div>
                    <button type="submit" class="hidden"></button>

                </form>

                @else

                @php
                $p1Winner = (int)$game->winner_id === (int)$game->player1_id;
                $p2Winner = (int)$game->winner_id === (int)$game->player2_id;
                @endphp

                <div class="space-y-2 text-sm">

                    <div class="flex justify-between items-center px-3 py-2 rounded
                        {{ $p1Winner ? 'bg-green-600/20 border border-green-500/40' : 'bg-gray-900 border border-gray-700 opacity-70' }}">

                        <span class="{{ $p1Winner ? 'text-green-400 font-semibold' : 'text-gray-400' }}">
                            {{ $game->player1->name ?? 'TBD' }}
                        </span>

                        <span class="font-mono">
                            {{ $game->player1_score ?? '-' }}
                        </span>
                    </div>

                    <div class="flex justify-between items-center px-3 py-2 rounded
                        {{ $p2Winner ? 'bg-green-600/20 border border-green-500/40' : 'bg-gray-900 border border-gray-700 opacity-70' }}">

                        <span class="{{ $p2Winner ? 'text-green-400 font-semibold' : 'text-gray-400' }}">
                            {{ $game->player2->name ?? 'TBD' }}
                        </span>

                        <span class="font-mono">
                            {{ $game->player2_score ?? '-' }}
                        </span>
                    </div>

                </div>

                @endif

            </div>

            @endforeach

        </div>

        @endforeach

    </div>

</div>

@endif