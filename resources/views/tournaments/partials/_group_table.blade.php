@php
    /*
    |--------------------------------------------------------------------------
    | 📊 TABELLENBERECHNUNG
    |--------------------------------------------------------------------------
    |
    | Berechnet die aktuelle Rangliste der Gruppe:
    | - Punkte
    | - Siege / Niederlagen
    | - Differenz
    |
    | Wichtig:
    | Diese Daten werden bei jedem AJAX-Reload neu berechnet.
    |
    */
    $table = app(\App\Services\Group\GroupTableCalculator::class)->calculate($group);
@endphp

<table class="w-full text-xs text-gray-300 border border-gray-700 rounded-lg overflow-hidden">

    {{-- 
    |--------------------------------------------------------------------------
    | 🧾 TABELLENKOPF
    |--------------------------------------------------------------------------
    |
    | Spalten:
    | #    = Platzierung
    | Sp   = Spiele
    | S/N  = Siege / Niederlagen
    | Diff = Punktedifferenz
    | Pkt  = Gesamtpunkte
    |
    --}}
    <thead class="bg-gray-800 text-gray-400 uppercase text-[10px] tracking-wider">
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
                /*
                |--------------------------------------------------------------------------
                | 🧠 STATUS LOGIK PRO SPIELER
                |--------------------------------------------------------------------------
                |
                | isQualified → kommt in die KO-Phase
                | isFirst     → Platz 1 (Gold / 🥇)
                | diff        → für farbliche Darstellung (+ grün / - rot)
                |
                */
                $isQualified = $index < $tournament->group_advance_count;
                $isFirst = $index === 0;
                $diff = $row['difference'];
            @endphp

            <tr
                class="
                    {{-- Qualifizierte Spieler leicht hervorheben --}}
                    {{ $isQualified ? 'bg-green-600/10' : 'bg-gray-900' }}

                    {{-- Hover Effekt für bessere Lesbarkeit --}}
                    hover:bg-gray-800 transition
                ">

                {{-- 
                |--------------------------------------------------------------------------
                | 🥇 PLATZIERUNG
                |--------------------------------------------------------------------------
                |
                | Platz 1 wird zusätzlich farblich hervorgehoben
                |
                --}}
                <td class="px-2 py-2 font-mono
                    {{ $isFirst ? 'text-yellow-400 font-bold' : '' }}">
                    {{ $index + 1 }}
                </td>

                {{-- 
                |--------------------------------------------------------------------------
                | 👤 SPIELERNAME
                |--------------------------------------------------------------------------
                |
                | - Qualifizierte Spieler grün
                | - Platz 1 bekommt 🥇
                |
                --}}
                <td class="px-2 py-2
                    {{ $isQualified ? 'text-green-400' : '' }}">

                    @if ($isFirst)
                        🥇
                    @endif

                    {{ $row['player']->name }}
                </td>

                {{-- 🔢 GESPIELTE SPIELE --}}
                <td class="text-center">
                    {{ $row['played'] }}
                </td>

                {{-- 🟢 SIEGE --}}
                <td class="text-center text-green-400">
                    {{ $row['wins'] }}
                </td>

                {{-- 🔴 NIEDERLAGEN --}}
                <td class="text-center text-red-400">
                    {{ $row['losses'] }}
                </td>

                {{-- 
                |--------------------------------------------------------------------------
                | 📈 DIFFERENZ
                |--------------------------------------------------------------------------
                |
                | + → grün
                | - → rot
                | 0 → neutral (grau)
                |
                | Zusätzlich wird ein "+" vor positive Werte gesetzt
                |
                --}}
                <td
                    class="text-center font-mono
                    {{ $diff > 0 ? 'text-green-400' : ($diff < 0 ? 'text-red-400' : 'text-gray-400') }}">

                    {{ $diff > 0 ? '+' : '' }}{{ $diff }}
                </td>

                {{-- 
                |--------------------------------------------------------------------------
                | ⭐ PUNKTE
                |--------------------------------------------------------------------------
                |
                | Wichtigste Kennzahl → fett
                | Qualifizierte zusätzlich grün
                |
                --}}
                <td class="text-center font-bold
                    {{ $isQualified ? 'text-green-400' : '' }}">
                    {{ $row['points'] }}
                </td>

            </tr>
        @endforeach

    </tbody>

</table>
