<nav class="bg-gray-900 border-b border-gray-800 px-6 py-3">
    <div class="flex items-center justify-between">

        {{-- Linke Seite --}}
        <div class="flex items-center gap-8">

            {{-- Logo --}}
            <a href="{{ route('tournaments.index') }}"
                class="flex items-center gap-3">

                <img
                    src="{{ asset('images/dart-manager-horizontal-normal.png') }}"
                    srcset="{{ asset('images/dart-manager-horizontal@2x.png') }} 2x"
                    height="50"
                    alt="Dart Manager Logo">

            </a>

            {{-- Alle Turniere --}}
            <a href="{{ route('tournaments.index') }}"
                class="text-gray-300 hover:text-white text-sm font-semibold transition">
                Alle Turniere
            </a>

            {{-- Aktive Turniere --}}
            @foreach($activeTournaments ?? [] as $tournament)

            <a href="{{ route('tournaments.show', $tournament) }}"
                class="text-gray-400 hover:text-emerald-400 text-sm transition">

                {{ $tournament->name }}

            </a>

            @endforeach

        </div>

        {{-- Rechte Seite --}}
        <div class="flex items-center gap-4">

            @auth
            <span class="text-gray-400 text-sm">
                {{ auth()->user()->name }}
            </span>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                    class="text-gray-400 hover:text-red-400 text-sm transition">
                    Logout
                </button>
            </form>
            @endauth

        </div>

    </div>
</nav>