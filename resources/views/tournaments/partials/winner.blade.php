@php

$finalMatch = null;

if ($tournament->matches) {

$finalMatch = $tournament->matches
->where('phase', 'knockout')
->last();

}

@endphp


@if($finalMatch && $finalMatch->winner)

<div class="card border-success mt-4">

    <div class="card-body">

        <h3 class="text-success">
            Sieger: {{ $finalMatch->winner->name }}
        </h3>

    </div>

</div>

@endif