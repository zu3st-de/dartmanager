<x-app-layout>
    <div x-data="{ showPlayers: {{ $tournament->status === 'draft' ? 'true' : 'false' }} }">
        <div class="space-y-8 py-8">

            <div class="h-full grid grid-cols-1 lg:grid-cols-3 gap-8">

                <!-- 1/3 Turnier Header -->
                <div class="bg-gray-900 border border-gray-800 rounded-xl p-6 shadow-lg">

                    <div class="flex justify-between items-center">
                        <h1 class="text-2xl font-semibold text-white">
                            {{ $tournament->name }}
                        </h1>

                        <span class="px-3 py-1 text-xs rounded-full
                @if($tournament->status === 'draft') bg-yellow-500/20 text-yellow-400
                @elseif($tournament->status === 'group_running') bg-blue-500/20 text-blue-400
                @elseif($tournament->status === 'ko_running') bg-purple-500/20 text-purple-400
                @elseif($tournament->status === 'finished') bg-green-500/20 text-green-400
                @endif
            ">
                            {{ ucfirst(str_replace('_', ' ', $tournament->status)) }}
                        </span>
                    </div>

                    <div class="text-gray-400 mt-2 text-sm">
                        Modus: {{ $tournament->mode }}
                    </div>

                </div>

                <!-- 2/3 Actions -->
                <div class="lg:col-span-2">
                    @include('tournaments.partials.actions')
                </div>

            </div>
            @include('tournaments.partials.players')
            @include('tournaments.partials.groups')
            @include('tournaments.partials.knockout')
            @include('tournaments.partials.winner')
        </div>
    </div>

</x-app-layout>