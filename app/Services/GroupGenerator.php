<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\Game;

class GroupGenerator
{
    public function generate(Tournament $tournament, int $groupCount): void
    {
        // Spieler zufällig mischen
        $players = $tournament->players()->inRandomOrder()->get();

        // Gruppen erstellen
        $groups = collect();

        for ($i = 0; $i < $groupCount; $i++) {
            $groups->push(
                $tournament->groups()->create([
                    'name' => chr(65 + $i) // A, B, C, ...
                ])
            );
        }

        // Spieler gleichmäßig auf Gruppen verteilen
        foreach ($players as $index => $player) {
            $group = $groups[$index % $groupCount];
            $player->update(['group_id' => $group->id]);
        }

        // Für jede Gruppe Round-Robin erzeugen
        foreach ($groups as $group) {

            $groupPlayers = $group->players;

            if ($groupPlayers->count() < 2) {
                continue;
            }

            $games = $this->generateRoundRobin($groupPlayers);

            $position = 1;

            foreach ($games as $gameData) {

                Game::create([
                    'tournament_id' => $tournament->id,
                    'group_id'      => $group->id,
                    'player1_id'    => $gameData['player1_id'],
                    'player2_id'    => $gameData['player2_id'],
                    'is_group_match' => true,
                    'round'         => $gameData['round'],
                    'position'      => $position++,
                    'best_of'       => $tournament->group_best_of ?? 3,
                ]);
            }
        }
    }

    /**
     * Dynamischer Round-Robin Generator (Circle Method)
     */
    private function generateRoundRobin($players)
    {
        /*
    |--------------------------------------------------------------------------
    | 1. Spieler zufällig mischen
    |--------------------------------------------------------------------------
    |
    | Dadurch ist der Spielplan jedes Turnier anders.
    | Sonst hätten immer die gleichen Spieler die gleichen Gegner früh/spät.
    |
    */

        $players = $players->shuffle()->values()->all();


        /*
    |--------------------------------------------------------------------------
    | 2. Spieleranzahl bestimmen
    |--------------------------------------------------------------------------
    */

        $count = count($players);


        /*
    |--------------------------------------------------------------------------
    | 3. Freilos hinzufügen wenn Spielerzahl ungerade ist
    |--------------------------------------------------------------------------
    |
    | Round Robin funktioniert nur mit einer GERADEN Anzahl Spieler,
    | weil immer Paare gebildet werden müssen.
    |
    | Beispiel:
    | 9 Spieler → einer hätte keinen Gegner.
    |
    | Lösung:
    | Wir fügen einen "Dummy-Spieler" (null) hinzu.
    |
    | Wer gegen null gepaart wird → hat Pause.
    |
    */

        if ($count % 2 !== 0) {
            $players[] = null;
            $count++;
        }


        /*
    |--------------------------------------------------------------------------
    | 4. Anzahl Runden berechnen
    |--------------------------------------------------------------------------
    |
    | Bei Round Robin gilt immer:
    |
    | Runden = Spieler - 1
    |
    | Beispiel:
    | 10 Spieler → 9 Runden
    |
    */

        $rounds = $count - 1;


        /*
    |--------------------------------------------------------------------------
    | 5. Spiele pro Runde
    |--------------------------------------------------------------------------
    |
    | Jede Runde entstehen:
    |
    | Spieler / 2 Paarungen
    |
    | Beispiel:
    | 10 Spieler → 5 Spiele
    |
    */

        $half = $count / 2;


        /*
    |--------------------------------------------------------------------------
    | 6. Ergebnisliste
    |--------------------------------------------------------------------------
    */

        $schedule = [];


        /*
    |--------------------------------------------------------------------------
    | 7. Runden erzeugen
    |--------------------------------------------------------------------------
    |
    | Wir erzeugen jetzt jede Runde einzeln.
    |
    */

        for ($round = 0; $round < $rounds; $round++) {

            /*
        |--------------------------------------------------------------------------
        | 8. Paarungen der aktuellen Runde
        |--------------------------------------------------------------------------
        |
        | Der Trick der Circle Method:
        |
        | erster Spieler gegen letzten
        | zweiter Spieler gegen vorletzten
        | dritter gegen drittletzten
        |
        */

            for ($i = 0; $i < $half; $i++) {

                $p1 = $players[$i];
                $p2 = $players[$count - 1 - $i];


                /*
            |--------------------------------------------------------------------------
            | 9. Freilos überspringen
            |--------------------------------------------------------------------------
            |
            | Wenn einer der beiden Spieler null ist,
            | bedeutet das Pause.
            |
            */

                if ($p1 && $p2) {

                    /*
                |--------------------------------------------------------------------------
                | 10. Seiten fairness herstellen
                |--------------------------------------------------------------------------
                |
                | Wenn wir nichts tun, wäre der erste Spieler
                | häufig Player1.
                |
                | Deshalb wechseln wir jede Runde die Seiten.
                |
                */

                    if ($round % 2 === 0) {
                        $player1 = $p1;
                        $player2 = $p2;
                    } else {
                        $player1 = $p2;
                        $player2 = $p1;
                    }


                    /*
                |--------------------------------------------------------------------------
                | 11. Spiel speichern
                |--------------------------------------------------------------------------
                */

                    $schedule[] = [
                        'round' => $round + 1,
                        'player1_id' => $player1->id,
                        'player2_id' => $player2->id,
                    ];
                }
            }


            /*
        |--------------------------------------------------------------------------
        | 12. Circle Method Rotation
        |--------------------------------------------------------------------------
        |
        | Jetzt rotieren wir die Spielerliste.
        |
        | WICHTIG:
        | Spieler an Position 0 bleibt fix.
        |
        | Beispiel vorher:
        |
        | A B C D E F G H I -
        |
        | nach Rotation:
        |
        | A - B C D E F G H I
        |
        */

            $last = array_pop($players);

            array_splice($players, 1, 0, [$last]);
        }


        /*
    |--------------------------------------------------------------------------
    | 13. Spiele zurückgeben
    |--------------------------------------------------------------------------
    */

        return collect($schedule);
    }
}
