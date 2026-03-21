<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Services\GroupTableCalculator;

class PublicController extends Controller
{
    /**
     * ================================================================
     * FOLLOW VIEW
     * ================================================================
     * Öffentliche Turnieransicht (Live / Follow)
     */
    public function follow(Tournament $tournament)
    {
        // Alle benötigten Relationen laden (Performance!)
        $tournament->load($this->relations());

        return view('public.follow', [
            'tournament'        => $tournament,
            'groupData'         => $this->buildGroupData($tournament),
            'players'           => $this->getPlayers($tournament),
            'koRounds'          => $this->buildKoRounds($tournament),
            'thirdPlaceMatches' => $this->getThirdPlaceMatches($tournament),
            ...$this->resolvePodium($tournament),
        ]);
    }


    /**
     * ================================================================
     * LIVE DATA (AJAX / POLLING)
     * ================================================================
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
    | Zentrale Definition aller benötigten Beziehungen
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
            'players' // 🔥 wichtig für Pre-Tournament
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | GROUP DATA
    |--------------------------------------------------------------------------
    | Bereitet Gruppen inkl. Tabelle + Spielstatus auf
    */
    private function buildGroupData(Tournament $tournament): array
    {
        return $tournament->groups->map(function ($group) {

            $games = $group->games;

            return [
                'group'       => $group,
                'table'       => app(GroupTableCalculator::class)->calculate($group),
                'games'       => $games,
                'lastGame'    => $games->whereNotNull('winner_id')->sortByDesc('updated_at')->first(),
                'currentGame' => $games->whereNull('winner_id')->sortBy('id')->first(),
                'nextGame'    => $games->whereNull('winner_id')->sortBy('id')->skip(1)->first(),
            ];
        })->toArray();
    }


    /*
    |--------------------------------------------------------------------------
    | KO ROUNDS
    |--------------------------------------------------------------------------
    | Gruppiert alle KO-Spiele nach Runden
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
    | PODIUM (1., 2., 3. Platz)
    |--------------------------------------------------------------------------
    */
    private function resolvePodium(Tournament $tournament): array
    {
        $winner = null;
        $secondPlace = null;
        $thirdPlace = null;

        $final = $tournament->games
            ->whereNull('group_id')
            ->sortByDesc('round')
            ->first();

        if ($final && $final->winner) {
            $winner = $final->winner;

            $secondPlace = $final->player1_id === $final->winner_id
                ? $final->player2
                : $final->player1;
        }

        $thirdPlaceMatch = $this->getThirdPlaceMatches($tournament)->first();

        if ($thirdPlaceMatch && $thirdPlaceMatch->winner) {
            $thirdPlace = $thirdPlaceMatch->winner;
        }

        return [
            'winner'      => $winner,
            'secondPlace' => $secondPlace,
            'thirdPlace'  => $thirdPlace,
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | PLAYERS (WICHTIG!)
    |--------------------------------------------------------------------------
    | Liefert:
    | - echte Turnier-Spieler (immer vorhanden)
    | - + bekannte Profi-Spieler (für Animation / Fallback)
    */
    private function getPlayers(Tournament $tournament)
    {
        // ✅ echte Spieler (Hauptquelle)
        $players = $tournament->players ?? collect();

        // 🔥 Profi-Spieler (immer gleiche Struktur!)
        $proPlayers = collect([
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
            'id'   => 'pro_' . md5($name),
            'name' => $name,
            'is_pro' => true
        ]);

        // 🔁 kombinieren + Duplikate entfernen
        return $players
            ->concat($proPlayers)
            ->unique('name')
            ->values();
    }
}
