<div class="card mb-4">
    <div class="card-body">

        <h4>Turnierinformationen</h4>

        <p><strong>Modus:</strong> {{ $tournament->mode }}</p>
        <p><strong>Status:</strong> {{ $tournament->status }}</p>
        <p><strong>Teilnehmer:</strong> {{ $tournament->players->count() }}</p>
        <p><strong>Gruppen:</strong> {{ $tournament->groups->count() }}</p>
        <p><strong>Weiter pro Gruppe:</strong> {{ $tournament->players_advancing }}</p>

    </div>
</div>