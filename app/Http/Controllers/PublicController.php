<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Services\Group\GroupTableCalculator;

/**
 * ================================================================
 * PublicController
 * ================================================================
 *
 * Öffentliche Turnieransicht (Follow View)
 *
 * Verantwortlich für:
 *
 * - Öffentliche Turnieranzeige
 * - Live Daten (AJAX)
 * - Gruppenphase Darstellung
 * - KO Phase Darstellung
 * - Podium Anzeige
 *
 */

class PublicController extends Controller
{

    /*
    |--------------------------------------------------------------------------
    | FOLLOW VIEW
    |--------------------------------------------------------------------------
    |
    | Öffentliche Turnierseite
    |
    | Beispiel:
    | /follow/ABC123
    |
    */

    public function follow(Tournament $tournament)
    {
        /*
        |--------------------------------------------------------------------------
        | Alle benötigten Relationen laden (Performance!)
        |--------------------------------------------------------------------------
        */

        $tournament->load($this->relations());


        /*
        |--------------------------------------------------------------------------
        | View zurückgeben
        |--------------------------------------------------------------------------
        */

        return view('public.follow', [
            'tournament'        => $tournament,
            'groupData'         => $this->buildGroupData($tournament),
            'players'           => $this->getPlayers($tournament),
            'koRounds'          => $this->buildKoRounds($tournament),
            'thirdPlaceMatches' => $this->getThirdPlaceMatches($tournament),

            // Podium Daten
            ...$this->resolvePodium($tournament),
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | LIVE DATA (Polling / AJAX)
    |--------------------------------------------------------------------------
    |
    | Wird regelmäßig vom Frontend abgefragt
    |
    */

    public function followData(Tournament $tournament)
    {
        $tournament->load($this->relations());

        return response()->json([
            'groups'            => $this->buildGroupData($tournament),
            'ko'                => $this->buildKoRounds($tournament),
            'tournament_status' => $tournament->status,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    |
    | Alle benötigten Beziehungen zentral definieren
    |
    */

    private function relations(): array
    {
        return [
            'groups.players',
            'groups.games.player1',
            'groups.games.player2',
            'games.player1',
            'games.player2',
            'games.winner',
            'players'
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | GROUP DATA
    |--------------------------------------------------------------------------
    |
    | Gruppen + Tabelle + Spielstatus vorbereiten
    |
    */

    private function buildGroupData(Tournament $tournament): array
    {
        return $tournament->groups->map(function ($group) {

            $games = $group->games;

            return [

                // Gruppe
                'group' => $group,

                // Tabelle berechnen
                'table' => app(GroupTableCalculator::class)
                    ->calculate($group),

                // Spiele
                'games' => $games,

                // Letztes Spiel
                'lastGame' => $games
                    ->whereNotNull('winner_id')
                    ->sortByDesc('updated_at')
                    ->first(),

                // Aktuelles Spiel
                'currentGame' => $games
                    ->whereNull('winner_id')
                    ->sortBy('id')
                    ->first(),

                // Nächstes Spiel
                'nextGame' => $games
                    ->whereNull('winner_id')
                    ->sortBy('id')
                    ->skip(1)
                    ->first(),
            ];
        })->values()->toArray();
    }


    /*
    |--------------------------------------------------------------------------
    | KO ROUNDS
    |--------------------------------------------------------------------------
    |
    | KO Spiele nach Runden gruppieren
    |
    */

    private function buildKoRounds(Tournament $tournament)
    {
        return $tournament->games
            ->whereNull('group_id')
            ->where('is_third_place', false)
            ->sortBy([
                ['round', 'asc'],
                ['position', 'asc']
            ])
            ->groupBy('round');
    }


    /*
    |--------------------------------------------------------------------------
    | THIRD PLACE MATCHES
    |--------------------------------------------------------------------------
    */

    private function getThirdPlaceMatches(Tournament $tournament)
    {
        return $tournament->games
            ->where('is_third_place', true);
    }


    /*
    |--------------------------------------------------------------------------
    | PODIUM
    |--------------------------------------------------------------------------
    |
    | 1. Platz
    | 2. Platz
    | 3. Platz
    |
    */

    private function resolvePodium(Tournament $tournament): array
    {
        $winner = null;
        $secondPlace = null;
        $thirdPlace = null;


        /*
        |--------------------------------------------------------------------------
        | Finale ermitteln
        |--------------------------------------------------------------------------
        */

        $final = $tournament->games
            ->whereNull('group_id')
            ->sortByDesc('round')
            ->first();


        /*
        |--------------------------------------------------------------------------
        | Gewinner bestimmen
        |--------------------------------------------------------------------------
        */

        if ($final?->winner) {

            $winner = $final->winner;

            $secondPlace =
                $final->player1_id === $final->winner_id
                ? $final->player2
                : $final->player1;
        }


        /*
        |--------------------------------------------------------------------------
        | Spiel um Platz 3
        |--------------------------------------------------------------------------
        */

        $thirdPlaceMatch = $this->getThirdPlaceMatches($tournament)->first();

        if ($thirdPlaceMatch?->winner) {
            $thirdPlace = $thirdPlaceMatch->winner;
        }


        return compact(
            'winner',
            'secondPlace',
            'thirdPlace'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | PLAYERS
    |--------------------------------------------------------------------------
    |
    | Turnierspieler + Pro Spieler
    |
    */

    private function getPlayers(Tournament $tournament)
    {
        $players = $tournament->players ?? collect();

        return $players
            ->concat($this->proPlayers())
            ->unique('name')
            ->values();
    }


    /*
    |--------------------------------------------------------------------------
    | PRO PLAYERS
    |--------------------------------------------------------------------------
    |
    | Demo Spieler für Animation
    |
    */

    private function proPlayers()
    {
        return collect([

            'Gabriel Clemens',
            'Martin Schindler',
            'Max Hopp',
            'Florian Hempel',
            'Ricardo Pietreczko',

            'Luke Humphries',
            'Michael van Gerwen',
            'Gerwyn Price',
            'Peter Wright',
            'Nathan Aspinall',

            'Rob Cross',
            'Jonny Clayton',
            'James Wade',
            'Gary Anderson',
            'Dave Chisnall',

            'Dimitri Van den Bergh',
            'Danny Noppert',
            'Chris Dobey',
            'Stephen Bunting',
            'Joe Cullen',

            'Luke Littler',
            'Gian van Veen',

        ])->map(fn($name) => (object) [

            'id' => 'pro_' . md5($name),
            'name' => $name,
            'is_pro' => true

        ]);
    }
}
