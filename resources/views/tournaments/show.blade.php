<x-app-layout>

    <div class="p-6">

        <h1 class="text-xl font-bold mb-6">
            {{ $tournament->name }}
        </h1>

        @include('tournaments.partials.info')

        @include('tournaments.partials.players')

        @include('tournaments.partials.actions')

        @include('tournaments.partials.groups')

        @include('tournaments.partials.knockout')

        @include('tournaments.partials.winner')

    </div>

</x-app-layout>