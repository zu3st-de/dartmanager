<div class="bg-gray-900 border border-gray-800 rounded-xl p-6 shadow-lg">

    {{-- HEADER --}}
    <div class="flex items-center justify-between mb-4">

        <h2 class="text-xl font-bold">Gruppenphase</h2>

        {{-- 🔥 AJAX Best-of --}}
        <select onchange="updateGroupBestOf(this, {{ $tournament->id }})"
            class="bg-gray-800 text-xs rounded border border-gray-700 
                       text-emerald-400 px-2 py-1">

            @foreach ([1, 3, 5, 7] as $value)
                <option value="{{ $value }}" {{ $groupBestOf == $value ? 'selected' : '' }}>
                    Bo{{ $value }}
                </option>
            @endforeach

        </select>

    </div>

    {{-- 🔹 GROUPS --}}
    <div class="flex gap-8 flex-wrap">

        @foreach ($tournament->groups as $group)
            <div class="min-w-[320px]" data-group="{{ $group->id }}">

                {{-- GROUP TITLE --}}
                <h3 class="text-sm text-gray-400 mb-4">
                    Gruppe {{ $group->name }}
                </h3>

                {{-- 
                    |--------------------------------------------------------------------------
                    | 📊 TABELLE (AJAX)
                    |--------------------------------------------------------------------------
                    --}}
                <div class="group-table mb-6" data-group-table="{{ $group->id }}">

                    @include('tournaments.partials._group_table', [
                        'group' => $group,
                        'tournament' => $tournament,
                    ])

                </div>

                {{-- 
                    |--------------------------------------------------------------------------
                    | 🎮 SPIELE (AJAX)
                    |--------------------------------------------------------------------------
                    |
                    | 🔥 HIER war vorher dein foreach → jetzt sauber ausgelagert
                    |
                    --}}
                <div data-group-games="{{ $group->id }}"
                    class="group-scroll max-h-[400px] overflow-y-auto pr-2 space-y-3 scroll-smooth">

                    @include('tournaments.partials._group_games', [
                        'group' => $group,
                        'tournament' => $tournament,
                    ])

                </div>

            </div>
        @endforeach

    </div>

</div>
