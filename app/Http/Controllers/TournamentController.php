<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\Game;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

use App\Services\Group\GroupGenerator;
use App\Services\Group\GroupTableCalculator;
use App\Services\Knockout\KnockoutGenerator;
use App\Services\Knockout\KnockoutAdvancer;

/**
 * ================================================================
 * TournamentController
 * ================================================================
 *
 * Zentraler Controller für die Verwaltung eines Turniers.
 *
 * Verantwortlichkeiten:
 *
 * - Turnier erstellen
 * - Spieler verwalten
 * - Turnier starten
 * - Scores speichern
 * - Gruppenphase beenden
 * - KO Phase starten
 * - Turnier zurücksetzen
 *
 * Der Controller delegiert komplexe Logik an Services:
 *
 * GroupGenerator        → Gruppen erstellen
 * GroupTableCalculator  → Gruppentabelle berechnen
 * KnockoutGenerator     → KO Baum erzeugen / befüllen
 * KnockoutAdvancer      → Gewinner in nächste Runde setzen
 *
 * ================================================================
 */

class TournamentController extends Controller
{
    /*
|--------------------------------------------------------------------------
| TURNIER ÜBERSICHT
|--------------------------------------------------------------------------
*/

    /*
|--------------------------------------------------------------------------
| Turnierübersicht anzeigen
|--------------------------------------------------------------------------
|
| Diese Methode lädt alle Turniere des aktuell angemeldeten Users
| und zeigt sie in der Übersicht an.
|
| Ablauf:
|
| 1. Turniere des eingeloggten Benutzers laden
| 2. Nach Erstellungsdatum sortieren (neueste zuerst)
| 3. An die View "tournaments.index" übergeben
|
| Zugriff:
| Route: GET /tournaments
|
| Rückgabe:
| Blade View mit der Turnierliste
|
*/

    public function index()
    {
        /*
    |--------------------------------------------------------------
    | Turniere des aktuellen Users laden |--------------------------------------------------------------
    |
    | auth()->user()
    | → aktuell eingeloggter Benutzer
    |
    | tournaments()
    | → Relationship User → Tournament
    |
    | latest()
    | → ORDER BY created_at DESC
    |
    */

        $tournaments = auth()->user()
            ->tournaments()
            ->whereNotIn('status', ['archived'])
            ->latest()
            ->get();


        /*
    |--------------------------------------------------------------
    | View zurückgeben
    |--------------------------------------------------------------
    |
    | Übergibt die Turniere an die Blade View:
    |
    | resources/views/tournaments/index.blade.php
    |
    */

        return view('tournaments.index', compact('tournaments'));
    }

    /*
|--------------------------------------------------------------------------
| Turnier-Erstellungsformular anzeigen
|--------------------------------------------------------------------------
|
| Diese Methode zeigt das Formular zum Erstellen eines neuen Turniers an.
|
| Ablauf:
|
| 1. Benutzer ruft die Seite zum Erstellen eines Turniers auf
| 2. Die entsprechende Blade-View wird geladen
|
| Zugriff:
| Route: GET /tournaments/create
|
| Hinweis:
| In dieser Methode werden noch keine Daten verarbeitet.
| Das eigentliche Erstellen des Turniers passiert später
| in der Methode `store()`.
|
| Rückgabe:
| Blade View mit dem Turnier-Erstellungsformular
|
*/

    public function create()
    {
        /*
    |--------------------------------------------------------------
    | View zurückgeben
    |--------------------------------------------------------------
    |
    | Lädt die Seite:
    |
    | resources/views/tournaments/create.blade.php
    |
    | Diese enthält das Formular zum Anlegen eines Turniers.
    |
    */

        return view('tournaments.create');
    }

    /*
|--------------------------------------------------------------------------
| TURNIER ERSTELLEN
|--------------------------------------------------------------------------
*/

    /*
|--------------------------------------------------------------------------
| Neues Turnier speichern
|--------------------------------------------------------------------------
|
| Diese Methode verarbeitet das Formular zum Erstellen eines neuen Turniers.
|
| Ablauf:
|
| 1. Formularvalidierung durchführen
| 2. Bei group_ko Modus zusätzliche Regeln prüfen
| 3. Turnier in der Datenbank erstellen
| 4. Benutzer zurück zur Turnierübersicht leiten
|
| Unterstützte Modi:
|
| ko
| → direktes KO Turnier
|
| group_ko
| → Gruppenphase + anschließende KO Phase
|
| Besonderheit bei group_ko:
|
| Die Anzahl der KO-Spieler muss eine Zweierpotenz sein
| (2, 4, 8, 16, 32, ...).
|
| Beispiel:
|
| 4 Gruppen
| 2 Aufsteiger pro Gruppe
|
| → 8 Spieler in KO Phase
|
*/

    public function store(Request $request)
    {
        /*
    |--------------------------------------------------------------
    | Grundvalidierung des Formulars
    |--------------------------------------------------------------
    |
    | name
    | → Turniername
    |
    | mode
    | → Turniermodus (ko oder group_ko)
    |
    | group_count
    | → Anzahl der Gruppen (optional)
    |
    | group_advance_count
    | → Anzahl der Aufsteiger pro Gruppe (optional)
    |
    */

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'mode' => 'required|in:ko,group_ko',
            'group_count' => 'nullable|integer|min:1',
            'group_advance_count' => 'nullable|integer|min:1',
        ]);


        /*
    |--------------------------------------------------------------
    | Zusätzliche Regeln für group_ko Turniere
    |--------------------------------------------------------------
    |
    | Hier wird überprüft:
    |
    | 1. Gruppenanzahl vorhanden
    | 2. Aufsteigeranzahl vorhanden
    | 3. Gesamtzahl der KO Spieler ist Zweierpotenz
    |
    */

        if ($validated['mode'] === 'group_ko') {

            /*
        |----------------------------------------------------------
        | Pflichtfelder für Gruppenmodus validieren
        |----------------------------------------------------------
        */

            $request->validate([
                'group_count' => 'required|integer|min:1',
                'group_advance_count' => 'required|integer|min:1',
            ]);


            /*
        |----------------------------------------------------------
        | Anzahl der KO Spieler berechnen
        |----------------------------------------------------------
        |
        | Beispiel:
        |
        | 4 Gruppen
        | 2 Aufsteiger
        |
        | → 8 KO Spieler
        |
        */

            $qualifiedCount =
                $validated['group_count'] *
                $validated['group_advance_count'];


            /*
        |----------------------------------------------------------
        | Prüfen ob Zweierpotenz
        |----------------------------------------------------------
        |
        | Beispiel gültig:
        |
        | 2,4,8,16,32
        |
        | Beispiel ungültig:
        |
        | 6,10,12
        |
        */

            $isPowerOfTwo =
                $qualifiedCount > 0 &&
                ($qualifiedCount & ($qualifiedCount - 1)) === 0;


            /*
        |----------------------------------------------------------
        | Fehler zurückgeben wenn ungültig
        |----------------------------------------------------------
        */

            if (!$isPowerOfTwo) {

                return back()
                    ->withErrors([
                        'group_advance_count' =>
                        'Die Gesamtzahl der KO-Teilnehmer muss eine 2er-Potenz sein.'
                    ])
                    ->withInput();
            }
        }


        /*
    |--------------------------------------------------------------
    | Turnier erstellen
    |--------------------------------------------------------------
    |
    | Das Turnier wird dem aktuell eingeloggten Benutzer
    | zugeordnet.
    |
    | Status:
    | draft → Turnier ist noch nicht gestartet
    |
    */

        auth()->user()->tournaments()->create([

            'name' => $validated['name'],

            'mode' => $validated['mode'],

            'group_count' => $validated['group_count'] ?? null,

            'group_advance_count' => $validated['group_advance_count'] ?? null,

            'has_lucky_loser' => $request->has('has_lucky_loser'),

            'has_third_place' => $request->has('has_third_place'),

            'status' => 'draft',
        ]);


        /*
    |--------------------------------------------------------------
    | Zur Turnierübersicht zurückleiten
    |--------------------------------------------------------------
    */

        return redirect()
            ->route('tournaments.index')
            ->with('success', 'Turnier erfolgreich erstellt.');
    }
    /*
|--------------------------------------------------------------------------
| TURNIER ANZEIGE
|--------------------------------------------------------------------------
*/
    /*
|--------------------------------------------------------------------------
| Turnier Detailansicht
|--------------------------------------------------------------------------
|
| Zeigt die komplette Verwaltungsseite eines Turniers an.
|
| Diese Seite enthält u.a.:
|
| - Spielerliste
| - Gruppenphase
| - Gruppenspiele
| - KO-Baum
| - Einstellungen (Best-of etc.)
|
| Ablauf:
|
| 1. Sicherheitsprüfung (Besitzer des Turniers)
| 2. Alle benötigten Relationen laden
| 3. KO-Runden ermitteln
| 4. Gruppenspiele vorbereiten
| 5. Gruppenspiel-Einstellungen ermitteln
| 6. Prüfen ob Gruppenspiele bereits Ergebnisse haben
| 7. Daten an Blade-View übergeben
|
| Route:
| GET /tournaments/{tournament}
|
*/

    public function show(Tournament $tournament)
    {
        /*
    |--------------------------------------------------------------------------
    | Sicherheitsprüfung
    |--------------------------------------------------------------------------
    |
    | Nur der Besitzer des Turniers darf diese Verwaltungsseite sehen.
    |
    */

        $this->authorizeTournament($tournament);


        /*
    |--------------------------------------------------------------------------
    | Benötigte Beziehungen (Relations) laden
    |--------------------------------------------------------------------------
    |
    | Dadurch vermeiden wir das sogenannte "N+1 Query Problem".
    |
    | Geladen werden:
    |
    | players
    | → alle Turnierspieler
    |
    | groups.players
    | → Spieler innerhalb der Gruppen
    |
    | groups.games.player1 / player2
    | → Gruppenspiele inklusive Spieler
    |
    | games.player1 / player2
    | → KO Spiele inklusive Spieler
    |
    */

        $tournament->load([
            'players',
            'groups.players',
            'groups.games.player1',
            'groups.games.player2',
            'games.player1',
            'games.player2',
        ]);


        /*
    |--------------------------------------------------------------------------
    | KO-Runden bestimmen
    |--------------------------------------------------------------------------
    |
    | Hier werden alle vorhandenen KO-Runden ermittelt.
    |
    | Beispiel:
    |
    | Achtelfinale → round = 1
    | Viertelfinale → round = 2
    | Halbfinale → round = 3
    | Finale → round = 4
    |
    | Das Spiel um Platz 3 wird bewusst ausgeschlossen.
    |
    */

        $koRounds = $tournament->games()
            ->where('round', '>', 0)
            ->where('is_third_place', false)
            ->select('round')
            ->distinct()
            ->orderBy('round')
            ->pluck('round');


        /*
    |--------------------------------------------------------------------------
    | Gruppenspiele abrufen
    |--------------------------------------------------------------------------
    |
    | Gruppenspiele haben:
    |
    | round = 0
    |
    */

        $groupGames = $tournament->games()
            ->where('round', 0);


        /*
    |--------------------------------------------------------------------------
    | Best-of Wert der Gruppenphase bestimmen
    |--------------------------------------------------------------------------
    |
    | Alle Gruppenspiele haben normalerweise den gleichen Best-of Wert.
    |
    | Hier wird der erste gefundene Wert verwendet.
    |
    | Fallback:
    | best_of = 1
    |
    */

        $groupBestOf = $tournament->games()
            ->whereNotNull('group_id')
            ->pluck('best_of')
            ->unique()
            ->first() ?? 1;


        /*
    |--------------------------------------------------------------------------
    | Prüfen ob Gruppenspiele bereits Ergebnisse haben
    |--------------------------------------------------------------------------
    |
    | Wird benötigt um später Einstellungen zu sperren.
    |
    | Beispiel:
    |
    | Wenn bereits Ergebnisse existieren,
    | darf Best-of nicht mehr geändert werden.
    |
    */

        $groupHasResults = $groupGames
            ->where(function ($q) {
                $q->whereNotNull('winner_id');
            })
            ->exists();


        /*
    |--------------------------------------------------------------------------
    | Daten an die View übergeben
    |--------------------------------------------------------------------------
    |
    | resources/views/tournaments/show.blade.php
    |
    */

        return view(
            'tournaments.show',
            compact(
                'tournament',
                'groupBestOf',
                'groupHasResults',
                'koRounds'
            )
        );
    }

    /*
|--------------------------------------------------------------------------
| SPIELER VERWALTEN
|--------------------------------------------------------------------------
*/

    /*
|--------------------------------------------------------------------------
| Spieler manuell hinzufügen
|--------------------------------------------------------------------------
|
| Fügt einen einzelnen Spieler zu einem Turnier hinzu.
|
| Ablauf:
|
| 1. Sicherheitsprüfung (Turnierbesitzer)
| 2. Prüfen ob Turnier noch im Draft-Modus ist
| 3. Eingabedaten validieren
| 4. Spieler erstellen
| 5. Optional JSON Response zurückgeben (für AJAX)
|
| Route:
| POST /tournaments/{tournament}/players
|
| Hinweis:
| Spieler können nur hinzugefügt werden,
| solange das Turnier noch nicht gestartet ist.
|
*/

    public function addPlayer(Request $request, Tournament $tournament)
    {
        /*
    |--------------------------------------------------------------------------
    | Sicherheitsprüfung
    |--------------------------------------------------------------------------
    */

        $this->authorizeTournament($tournament);


        /*
    |--------------------------------------------------------------------------
    | Turnierstatus prüfen
    |--------------------------------------------------------------------------
    |
    | Spieler dürfen nur hinzugefügt werden,
    | solange das Turnier noch im Draft-Modus ist.
    |
    */

        if ($tournament->status !== 'draft') {
            abort(400);
        }


        /*
    |--------------------------------------------------------------------------
    | Eingabedaten validieren
    |--------------------------------------------------------------------------
    */

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);


        /*
    |--------------------------------------------------------------------------
    | Spieler erstellen
    |--------------------------------------------------------------------------
    */

        $player = $tournament->players()->create([
            'name' => $validated['name'],
        ]);


        /*
    |--------------------------------------------------------------------------
    | JSON Response für AJAX Requests
    |--------------------------------------------------------------------------
    |
    | Wird verwendet wenn Spieler dynamisch über
    | JavaScript hinzugefügt werden.
    |
    */

        if ($request->expectsJson()) {

            return response()->json([
                'id' => $player->id,
                'name' => $player->name,
            ]);
        }


        /*
    |--------------------------------------------------------------------------
    | Standard Redirect
    |--------------------------------------------------------------------------
    */

        return back();
    }/*
|--------------------------------------------------------------------------
| Mehrere Spieler importieren
|--------------------------------------------------------------------------
|
| Ermöglicht das Einfügen mehrerer Spieler gleichzeitig.
|
| Typischer Anwendungsfall:
|
| Spieler aus Excel oder einer Liste kopieren:
|
| Spieler 1
| Spieler 2
| Spieler 3
|
| Ablauf:
|
| 1. Sicherheitsprüfung
| 2. Turnierstatus prüfen
| 3. Text in einzelne Zeilen aufteilen
| 4. Namen bereinigen
| 5. Spieler erstellen
|
*/

    public function bulkPlayers(Request $request, Tournament $tournament)
    {
        /*
    |--------------------------------------------------------------------------
    | Sicherheitsprüfung
    |--------------------------------------------------------------------------
    */

        $this->authorizeTournament($tournament);


        /*
    |--------------------------------------------------------------------------
    | Turnierstatus prüfen
    |--------------------------------------------------------------------------
    */

        if ($tournament->status !== 'draft') {

            return back()->with(
                'error',
                'Spieler können nur im Draft hinzugefügt werden.'
            );
        }


        /*
    |--------------------------------------------------------------------------
    | Eingabe validieren
    |--------------------------------------------------------------------------
    */

        $request->validate([
            'bulk_players' => 'required|string'
        ]);


        /*
    |--------------------------------------------------------------------------
    | Text in einzelne Zeilen aufteilen
    |--------------------------------------------------------------------------
    */

        $lines = preg_split('/\r\n|\r|\n/', $request->bulk_players);

        $count = 0;


        /*
    |--------------------------------------------------------------------------
    | Jede Zeile verarbeiten
    |--------------------------------------------------------------------------
    */

        foreach ($lines as $line) {

            $name = trim($line);

            /*
        |--------------------------------------------------------------
        | Leere Zeilen überspringen
        |--------------------------------------------------------------
        */

            if ($name === '') {
                continue;
            }


            /*
        |--------------------------------------------------------------
        | Duplikate vermeiden
        |--------------------------------------------------------------
        */

            if ($tournament->players()->where('name', $name)->exists()) {
                continue;
            }


            /*
        |--------------------------------------------------------------
        | Excel-Tabellen bereinigen
        |--------------------------------------------------------------
        |
        | Wenn Daten aus Excel kopiert werden,
        | können Tabs enthalten sein.
        |
        */

            $name = explode("\t", $name)[0];


            /*
        |--------------------------------------------------------------
        | Spieler erstellen
        |--------------------------------------------------------------
        */

            $tournament->players()->create([
                'name' => $name
            ]);

            $count++;
        }


        /*
    |--------------------------------------------------------------------------
    | Erfolgsnachricht
    |--------------------------------------------------------------------------
    */

        return back()->with(
            'success',
            "$count Spieler importiert."
        );
    }
    /*
|--------------------------------------------------------------------------
| Spieler auslosen (Seed setzen)
|--------------------------------------------------------------------------
|
| Diese Methode mischt alle Turnierspieler zufällig
| und weist ihnen eine Seed-Position zu.
|
| Beispiel:
|
| Spieler A → Seed 1
| Spieler B → Seed 2
| Spieler C → Seed 3
|
| Diese Seeds werden später verwendet für:
|
| - Gruppenverteilung
| - KO-Bracket Platzierung
|
| Ablauf:
|
| 1. Turnierstatus prüfen
| 2. Spieler zufällig sortieren
| 3. Seeds vergeben
|
*/

    public function draw(Tournament $tournament)
    {
        /*
    |--------------------------------------------------------------------------
    | Turnierstatus prüfen
    |--------------------------------------------------------------------------
    */

        if ($tournament->status !== 'draft') {

            return back()->with(
                'error',
                'Auslosung nur im Entwurfsmodus möglich.'
            );
        }


        /*
    |--------------------------------------------------------------------------
    | Auslosung innerhalb einer Datenbank-Transaktion
    |--------------------------------------------------------------------------
    |
    | lockForUpdate verhindert,
    | dass parallel Änderungen passieren.
    |
    */

        DB::transaction(function () use ($tournament) {

            /*
        |--------------------------------------------------------------
        | Spieler zufällig sortieren
        |--------------------------------------------------------------
        */

            $players = $tournament->players()
                ->inRandomOrder()
                ->lockForUpdate()
                ->get();


            /*
        |--------------------------------------------------------------
        | Seed Nummern vergeben
        |--------------------------------------------------------------
        */

            foreach ($players as $index => $player) {

                $player->update([
                    'seed' => $index + 1
                ]);
            }
        });


        /*
    |--------------------------------------------------------------------------
    | Erfolgsnachricht
    |--------------------------------------------------------------------------
    */

        return back()->with(
            'success',
            'Auslosung durchgeführt.'
        );
    }

    /*
|--------------------------------------------------------------------------
| TURNIER STARTEN
|--------------------------------------------------------------------------
|
| Diese Methode startet das Turnier abhängig vom gewählten Modus.
|
| Unterstützte Modi:
|
| ko
| → direktes KO Turnier ohne Gruppenphase
|
| group_ko
| → zuerst Gruppenphase, danach KO Phase
|
| Ablauf:
|
| 1. Sicherheitsprüfung (Turnierbesitzer)
| 2. Prüfen ob Turnier noch im Draft Status ist
| 3. Prüfen ob genügend Spieler vorhanden sind
| 4. Startlogik innerhalb einer Datenbank-Transaktion:
|
|    GROUP_KO:
|      - Gruppen erzeugen
|      - Gruppenspiele generieren
|      - KO-Baum vorbereiten (ohne Spieler)
|      - Turnierstatus → group_running
|
|    KO:
|      - KO-Baum erzeugen
|      - Spieler sofort einsetzen
|      - Turnierstatus → ko_running
|
| Route:
| POST /tournaments/{tournament}/start
|
*/

    public function start(Tournament $tournament)
    {
        /*
    |--------------------------------------------------------------------------
    | Sicherheitsprüfung
    |--------------------------------------------------------------------------
    |
    | Nur der Besitzer des Turniers darf es starten.
    |
    */

        $this->authorizeTournament($tournament);


        /*
    |--------------------------------------------------------------------------
    | Turnierstatus prüfen
    |--------------------------------------------------------------------------
    |
    | Ein Turnier darf nur gestartet werden,
    | wenn es sich noch im Draft-Modus befindet.
    |
    */

        if ($tournament->status !== 'draft') {

            return back()->with(
                'error',
                'Turnier wurde bereits gestartet.'
            );
        }


        /*
    |--------------------------------------------------------------------------
    | Sicherstellen dass genügend Spieler vorhanden sind
    |--------------------------------------------------------------------------
    |
    | Ein Turnier benötigt mindestens zwei Spieler.
    |
    */

        if ($tournament->players()->count() < 2) {

            return back()->with(
                'error',
                'Mindestens zwei Spieler erforderlich.'
            );
        }


        /*
    |--------------------------------------------------------------------------
    | Turnierstart innerhalb einer Transaktion
    |--------------------------------------------------------------------------
    |
    | Dadurch wird sichergestellt, dass bei einem Fehler
    | keine halbfertigen Daten (z.B. Gruppen ohne Spiele)
    | in der Datenbank verbleiben.
    |
    */

        DB::transaction(function () use ($tournament) {

            /*
        |--------------------------------------------------------------------------
        | Gruppen + KO Turnier
        |--------------------------------------------------------------------------
        |
        | Ablauf:
        |
        | 1. Gruppen generieren
        | 2. Gruppenspiele erstellen
        | 3. KO-Bracket vorbereiten (ohne Spieler)
        |
        */

            if ($tournament->mode === 'group_ko') {

                /*
            |--------------------------------------------------------------
            | Gruppen erzeugen
            |--------------------------------------------------------------
            */

                app(GroupGenerator::class)
                    ->generate($tournament, $tournament->group_count);
                /*
            |--------------------------------------------------------------
            | KO Baum vorbereiten
            |--------------------------------------------------------------
            |
            | Der KO-Baum wird bereits erstellt, aber noch ohne Spieler.
            | Diese werden erst nach Abschluss der Gruppenphase eingesetzt.
            |
            */
                $size = $tournament->group_count * $tournament->group_advance_count;

                app(KnockoutGenerator::class)
                    ->generatePlaceholderBracket($tournament, $size);


                /*
            |--------------------------------------------------------------
            | Turnierstatus aktualisieren
            |--------------------------------------------------------------
            */

                $tournament->update([
                    'status' => 'group_running'
                ]);
            }

            /*
        |--------------------------------------------------------------------------
        | Direktes KO Turnier
        |--------------------------------------------------------------------------
        |
        | Ablauf:
        |
        | 1. KO Baum erzeugen
        | 2. Spieler sofort einsetzen
        |
        */ else {

                /*
    |--------------------------------------------------------------------------
    | 🔹 Spieler laden (Reihenfolge ist wichtig!)
    |--------------------------------------------------------------------------
    |
    | Hier kannst du steuern:
    |
    | - ->orderBy('seed')        → für gesetzte Turniere
    | - ->inRandomOrder()        → für zufällige Turniere
    |
    | Aktuell: neutral (DB Reihenfolge)
    |
    */
                $players = $tournament->players()->get()->values();

                app(KnockoutGenerator::class)
                    ->generateDirectBracket($tournament, $players);


                /*
    |--------------------------------------------------------------------------
    | 🔹 Turnierstatus setzen
    |--------------------------------------------------------------------------
    |
    | Das Turnier läuft jetzt im KO-Modus.
    |
    */
                $tournament->update([
                    'status' => 'ko_running'
                ]);
            }
        });


        /*
    |--------------------------------------------------------------------------
    | Zur Turnierseite zurückkehren
    |--------------------------------------------------------------------------
    */
        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'fullReload' => true
            ]);
        }

        return redirect()
            ->route('tournaments.show', $tournament)
            ->with('success', 'Turnier erfolgreich gestartet.');
    }

    public function updateScore(Request $request, Game $game)
    {
        /*
    |--------------------------------------------------------------------------
    | Turnier ermitteln + Sicherheitsprüfung
    |--------------------------------------------------------------------------
    */

        $tournament = $game->tournament;

        $this->authorizeTournament($tournament);


        /*
    |--------------------------------------------------------------------------
    | Prüfen ob Spiel bereits entschieden ist
    |--------------------------------------------------------------------------
    |
    | Ein Spiel darf nur einmal abgeschlossen werden.
    |
    */

        if ($game->winner_id) {

            if ($request->expectsJson()) {

                return response()->json([
                    'success' => true,

                    'game_id' => $game->id,
                    'group_id' => $game->group_id,

                    // Ergebnisdaten
                    'winner_id' => $game->winner_id,
                    'player1_score' => $game->player1_score,
                    'player2_score' => $game->player2_score,
                    'winning_rest' => $game->winning_rest,
                    'best_of' => $game->best_of,
                ]);
            }

            return back();
        }

        if (!$game->player1_id || !$game->player2_id) {

            $message = 'Ergebnisse können erst eingetragen werden, wenn beide Teilnehmer feststehen.';

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => $message,
                ], 422);
            }

            return back()->withErrors([
                'score' => $message,
            ]);
        }


        /*
    |--------------------------------------------------------------------------
    | Eingaben validieren
    |--------------------------------------------------------------------------
    |
    | player1_score / player2_score
    | → Anzahl gewonnener Legs
    |
    | winning_rest
    | → Restpunktzahl beim letzten Leg
    |    (z.B. 32 bei Doppel 16)
    |
    */

        $validated = $request->validate([
            'player1_score' => 'required|integer|min:0',
            'player2_score' => 'required|integer|min:0',
            'winning_rest'  => 'nullable|integer|min:0|max:501',
        ]);


        $player1Score = (int) $validated['player1_score'];
        $player2Score = (int) $validated['player2_score'];
        $winningRest  = $validated['winning_rest'] ?? null;


        /*
    |--------------------------------------------------------------------------
    | Best-of Ergebnis validieren
    |--------------------------------------------------------------------------
    |
    | Diese Logik befindet sich bewusst im Model (Game::validateResult),
    | damit sie zentral wiederverwendbar bleibt.
    |
    */

        try {

            $game->validateResult(
                $player1Score,
                $player2Score,
                $winningRest
            );
        } catch (\Throwable $e) {

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => $e->getMessage(),
                ], 422);
            }

            return back()->withErrors($e->getMessage());
        }


        /*
    |--------------------------------------------------------------------------
    | Scores speichern
    |--------------------------------------------------------------------------
    |
    | winning_rest wird nur gespeichert wenn:
    |
    | - Best-of = 1
    | - Gruppenspiel
    |
    */

        $game->update([
            'player1_score' => $player1Score,
            'player2_score' => $player2Score,
            'winning_rest'  => ($game->best_of == 1 && $game->group_id !== null)
                ? $winningRest
                : null,
        ]);


        /*
    |--------------------------------------------------------------------------
    | Gewinner bestimmen
    |--------------------------------------------------------------------------
    |
    | Beispiel:
    |
    | Best-of 5
    | → firstTo = 3
    |
    */

        $firstTo = (int) ceil($game->best_of / 2);

        $winnerId = null;

        if ($player1Score >= $firstTo) {
            $winnerId = $game->player1_id;
        } elseif ($player2Score >= $firstTo) {
            $winnerId = $game->player2_id;
        }


        /*
    |--------------------------------------------------------------------------
    | KO-Engine ausführen
    |--------------------------------------------------------------------------
    |
    | Sobald ein Gewinner feststeht übernimmt die KnockoutAdvancer:
    |
    | - winner_id setzen
    | - nächsten Gegner bestimmen
    | - Finale erkennen
    | - Turniersieger setzen
    |
    */

        if ($winnerId) {

            app(KnockoutAdvancer::class)
                ->handleWin($game, $winnerId);
        }


        /*
        
    |--------------------------------------------------------------------------
    | Spiel aktualisieren
    |--------------------------------------------------------------------------
    |
    | refresh() lädt die neuen Werte aus der Datenbank.
    |
    */

        $game->refresh();


        /*
|--------------------------------------------------------------------------
| 🔥 NÄCHSTES SPIEL ERMITTELN (NEU)
|--------------------------------------------------------------------------
|
| KO Logik:
| nächstes Spiel = round + 1
| position = ceil(position / 2)
|
*/
        $nextGame = null;

        if ($game->group_id === null && $game->round !== null) {

            $nextGame = \App\Models\Game::where('tournament_id', $tournament->id)
                ->where('round', $game->round + 1)
                ->where('position', (int) ceil($game->position / 2))
                ->with(['player1', 'player2'])
                ->first();
        }


        /*
    |--------------------------------------------------------------------------
    | JSON Response (AJAX)
    |--------------------------------------------------------------------------
    |
    | Wird z.B. bei Live-Score Eingabe verwendet.
    |
    */

        if ($request->expectsJson()) {

            return response()->json([
                'success' => true,
                'game_id' => $game->id,
                'group_id' => $game->group_id,
                'winner_id' => $game->winner_id,
                'player1_score' => $game->player1_score,
                'player2_score' => $game->player2_score,
                // nächstes Spiel
                'next_game_id' => $nextGame?->id,

                'next_player1_id' => $nextGame?->player1_id,
                'next_player2_id' => $nextGame?->player2_id,

                // Namen direkt für UI
                'next_player1_name' => optional($nextGame?->player1)->name,
                'next_player2_name' => optional($nextGame?->player2)->name,
            ]);
        }


        /*
    |--------------------------------------------------------------------------
    | Standard Redirect
    |--------------------------------------------------------------------------
    */

        return back();
    }

    /*
|--------------------------------------------------------------------------
| GRUPPENPHASE
|--------------------------------------------------------------------------
*/

    /*
|--------------------------------------------------------------------------
| Best-of der Gruppenspiele ändern (AJAX)
|--------------------------------------------------------------------------
|
| Diese Methode aktualisiert den Best-of Wert für ALLE Spiele
| der Gruppenphase eines Turniers.
|
| WICHTIG:
| - Der Best-of Wert wird NICHT im Tournament gespeichert,
|   sondern direkt in den einzelnen Game-Datensätzen.
|
| - Dadurch bleibt das System flexibel:
|   → unterschiedliche Best-of Werte pro Spiel möglich
|
| Ablauf:
|
| 1. Sicherheitsprüfung (nur Turnierbesitzer)
| 2. Eingabe validieren
| 3. Alle Gruppenspiele aktualisieren
| 4. JSON Response zurückgeben (für AJAX)
|
| Route:
| POST /tournaments/{tournament}/group-best-of
|
| Erwartete Eingabe:
| best_of → 1, 3, 5 oder 7
|
| Beispiel:
| best_of = 3 → First to 2 Legs
|
*/

    public function updateGroupBestOf(Request $request, Tournament $tournament)
    {
        /*
    |--------------------------------------------------------------------------
    | 🔐 Sicherheitsprüfung
    |--------------------------------------------------------------------------
    |
    | Nur der Besitzer des Turniers darf Änderungen durchführen.
    |
    */
        $this->authorizeTournament($tournament);


        /*
    |--------------------------------------------------------------------------
    | ✅ Validierung der Eingabedaten
    |--------------------------------------------------------------------------
    |
    | best_of:
    | → Muss vorhanden sein
    | → numerisch
    | → nur erlaubte Werte (1,3,5,7)
    |
    */
        $validated = $request->validate([
            'best_of' => 'required|numeric|in:1,3,5,7',
        ]);


        /*
    |--------------------------------------------------------------------------
    | 💾 Best-of auf alle Gruppenspiele anwenden
    |--------------------------------------------------------------------------
    |
    | Es werden ausschließlich Spiele mit group_id berücksichtigt,
    | da diese zur Gruppenphase gehören.
    |
    | KO-Spiele bleiben unverändert.
    |
    */
        Game::where('tournament_id', $tournament->id)
            ->whereNotNull('group_id')
            ->update([
                'best_of' => (int) $validated['best_of']
            ]);


        /*
    |--------------------------------------------------------------------------
    | 📦 JSON Response für AJAX
    |--------------------------------------------------------------------------
    |
    | Wird im Frontend verwendet, um:
    | - Reload auszulösen
    | - oder später UI dynamisch zu aktualisieren
    |
    */
        return response()->json([
            'success' => true
        ]);
    }

    /*
|--------------------------------------------------------------------------
| Gruppenphase abschließen
|--------------------------------------------------------------------------
|
| Diese Methode beendet offiziell die Gruppenphase
| und startet anschließend die KO-Phase.
|
| Ablauf:
|
| 1. Prüfen ob noch Gruppenspiele offen sind
| 2. Falls ja → Fehlermeldung
| 3. Falls nein → KO Phase starten
|
| Route:
| POST /tournaments/{tournament}/finish-groups
|
| Hinweis:
|
| Die eigentliche KO-Erstellung passiert
| in der Methode startKo().
|
*/

    public function finishGroups(Tournament $tournament)
    {
        /*
    |--------------------------------------------------------------------------
    | Sicherheitsprüfung
    |--------------------------------------------------------------------------
    */

        $this->authorizeTournament($tournament);


        /*
    |--------------------------------------------------------------------------
    | Prüfen ob noch Gruppenspiele offen sind
    |--------------------------------------------------------------------------
    |
    | Ein Gruppenspiel gilt als abgeschlossen,
    | wenn winner_id gesetzt ist.
    |
    */

        $unfinished = Game::where('tournament_id', $tournament->id)
            ->whereNotNull('group_id')
            ->whereNull('winner_id')
            ->exists();


        /*
    |--------------------------------------------------------------------------
    | Abbruch wenn noch Spiele offen sind
    |--------------------------------------------------------------------------
    */

        if ($unfinished) {

            return back()->with(
                'error',
                'Nicht alle Gruppenspiele sind abgeschlossen.'
            );
        }


        /*
    |--------------------------------------------------------------------------
    | KO Phase starten
    |--------------------------------------------------------------------------
    |
    | Diese Methode übernimmt:
    |
    | - Qualifizierte Spieler bestimmen
    | - KO Bracket befüllen
    | - Turnierstatus aktualisieren
    |
    */

        return $this->startKo($tournament);
    }

    /*
|--------------------------------------------------------------------------
| KO PHASE
|--------------------------------------------------------------------------
*/

    /**
     * ================================================================
     * Start KO Phase
     * ================================================================
     *
     * Startet die KO-Phase eines Turniers basierend auf den Ergebnissen
     * der Gruppenphase.
     *
     * Ablauf:
     * 1. Tabellen für alle Gruppen berechnen
     * 2. Top X Spieler je Gruppe bestimmen (group_advance_count)
     * 3. Spieler ins KO-Bracket einfügen
     * 4. KO-Phase starten
     *
     * Optional:
     * - Wenn `has_lucky_loser = true`, wird zusätzlich ein
     *   separates Lucky-Loser-Turnier erstellt:
     *     → enthält alle nicht qualifizierten Spieler
     *     → startet im Draft-Modus (kein Auto-Start)
     *
     * WICHTIG:
     * - KO-Logik wird vollständig über KnockoutGenerator abgewickelt
     * - Keine direkte Manipulation von Games außerhalb der Engine
     *
     * @param Tournament $tournament
     * @return RedirectResponse
     */

    public function startKo(Tournament $tournament)
    {
        /*
    |--------------------------------------------------------------------------
    | Sicherheitsprüfung
    |--------------------------------------------------------------------------
    |
    | Nur der Besitzer des Turniers darf die KO Phase starten.
    |
    */

        $this->authorizeTournament($tournament);


        /*
    |--------------------------------------------------------------------------
    | Datenbank Transaktion
    |--------------------------------------------------------------------------
    |
    | Falls beim Einsetzen der Spieler ein Fehler passiert,
    | wird alles zurückgerollt.
    |
    */

        DB::transaction(function () use ($tournament) {

            /*
        |--------------------------------------------------------------------------
        | Gruppentabellen berechnen
        |--------------------------------------------------------------------------
        |
        | Für jede Gruppe wird die Tabelle berechnet.
        | Diese enthält:
        |
        | [
        |   0 => 1. Platz
        |   1 => 2. Platz
        |   2 => 3. Platz
        |   ...
        | ]
        |
        */

            $tables = [];

            foreach ($tournament->groups as $group) {

                $tables[$group->name] =
                    app(GroupTableCalculator::class)
                    ->calculate($group);
            }


            /*
        |--------------------------------------------------------------------------
        | Alle KO Spiele laden
        |--------------------------------------------------------------------------
        |
        | KO Spiele erkennt man daran, dass sie keiner Gruppe gehören.
        |
        */

            $games = $tournament->games()
                ->whereNull('group_id')
                ->get();


            /*
        |--------------------------------------------------------------------------
        | Spieler anhand der Sources einsetzen
        |--------------------------------------------------------------------------
        |
        | Beispiel:
        |
        | A1 -> 1. Platz Gruppe A
        | B4 -> 4. Platz Gruppe B
        |
        */

            foreach ($games as $game) {

                /*
            |--------------------------------------------------------------
            | Player 1 einsetzen
            |--------------------------------------------------------------
            */

                if ($game->player1_source) {

                    $game->player1_id = $this->resolveGroupSource(
                        $game->player1_source,
                        $tables
                    );
                }


                /*
            |--------------------------------------------------------------
            | Player 2 einsetzen
            |--------------------------------------------------------------
            */

                if ($game->player2_source) {

                    $game->player2_id = $this->resolveGroupSource(
                        $game->player2_source,
                        $tables
                    );
                }

                $game->save();
            }


            /*
        |--------------------------------------------------------------------------
        | Turnierstatus ändern
        |--------------------------------------------------------------------------
        */

            $tournament->update([
                'status' => 'ko_running'
            ]);

            // 🔥 Lucky Loser Turnier erstellen
            if ($tournament->has_lucky_loser) {
                $this->createLuckyLoserTournament($tournament, $tables);
            }
        });


        /*
    |--------------------------------------------------------------------------
    | Zur Turnierseite zurückkehren
    |--------------------------------------------------------------------------
    */

        return redirect()
            ->route('tournaments.show', $tournament)
            ->with('success', 'KO Phase gestartet.');
    }
    /*
|--------------------------------------------------------------------------
| Qualifizierte Spieler für KO Phase bestimmen
|--------------------------------------------------------------------------
|
| Diese Methode ermittelt, welche Spieler aus der Gruppenphase
| in die KO Phase einziehen.
|
| Ablauf:
|
| 1. Alle Gruppen laden
| 2. Gruppentabellen berechnen
| 3. Top-Platzierungen jeder Gruppe übernehmen
| 4. Restspieler sammeln
| 5. Falls notwendig Lucky-Loser bestimmen
| 6. Liste der KO-Spieler zurückgeben
|
| Beispiel:
|
| 4 Gruppen
| 2 Aufsteiger pro Gruppe
|
| → 8 Spieler für KO Phase
|
*/

    private function getKoQualifiedPlayers(Tournament $tournament)
    {
        /*
    |--------------------------------------------------------------------------
    | Gruppen laden
    |--------------------------------------------------------------------------
    */

        $groups = $tournament->groups()
            ->orderBy('name')
            ->get();


        /*
    |--------------------------------------------------------------------------
    | Anzahl der Aufsteiger pro Gruppe
    |--------------------------------------------------------------------------
    */

        $advance = $tournament->group_advance_count;


        /*
    |--------------------------------------------------------------------------
    | Sammlungen vorbereiten
    |--------------------------------------------------------------------------
    |
    | qualified
    | → direkte Aufsteiger
    |
    | remaining
    | → mögliche Lucky Loser
    |
    */

        $qualified = collect();
        $remaining = collect();


        /*
    |--------------------------------------------------------------------------
    | Gruppentabellen berechnen
    |--------------------------------------------------------------------------
    */

        foreach ($groups as $group) {

            $table = app(GroupTableCalculator::class)
                ->calculate($group);


            foreach ($table as $index => $row) {

                /*
            |----------------------------------------------------------
            | Direkte Qualifikation
            |----------------------------------------------------------
            */

                if ($index < $advance) {

                    $qualified->push($row);
                } else {

                    /*
                |------------------------------------------------------
                | Restspieler sammeln
                |------------------------------------------------------
                |
                | Diese Spieler können ggf. als Lucky Loser
                | nachrücken.
                |
                */

                    $remaining->push($row);
                }
            }
        }


        /*
    |--------------------------------------------------------------------------
    | Zielgröße der KO Phase bestimmen
    |--------------------------------------------------------------------------
    |
    | KO Turniere benötigen eine Zweierpotenz.
    |
    | Beispiel:
    |
    | 6 Spieler → 8er KO
    |
    */

        $total = $qualified->count();

        $targetSize = 2 ** ceil(log($total, 2));


        /*
    |--------------------------------------------------------------------------
    | Lucky Loser bestimmen
    |--------------------------------------------------------------------------
    */

        if ($total < $targetSize) {

            $needed = $targetSize - $total;

            $bestRemaining = $remaining
                ->sortBy([
                    ['points', 'desc'],
                    ['difference', 'desc']
                ])
                ->take($needed);

            $qualified = $qualified->merge($bestRemaining);
        }


        /*
    |--------------------------------------------------------------------------
    | Nur Spieler zurückgeben
    |--------------------------------------------------------------------------
    */

        return $qualified
            ->pluck('player')
            ->values();
    }
    /*
|--------------------------------------------------------------------------
| TURNIER RESET
|--------------------------------------------------------------------------
*/

    /**
     * Setzt das komplette Turnier zurück
     *
     * Diese Methode löscht:
     * - Alle Spiele
     * - Alle Gruppen
     * - Setzt den Turnierstatus zurück auf "draft"
     *
     * Unterstützt sowohl:
     * - Klassischen Request (Redirect zurück zur Seite)
     * - AJAX Request (JSON Response mit fullReload)
     *
     * Sicherheitsmaßnahmen:
     * - Turniername muss zur Bestätigung übergeben werden
     * - Nur berechtigte Benutzer dürfen resetten
     */
    public function reset(Request $request, Tournament $tournament)
    {
        /**
         * Sicherheitscheck:
         * Prüft ob der aktuelle Benutzer Zugriff auf das Turnier hat
         */
        $this->authorizeTournament($tournament);

        /**
         * Validierung:
         * Der Benutzer muss den Turniernamen zur Bestätigung eingeben
         * Dadurch wird verhindert, dass versehentlich ein Turnier gelöscht wird
         */
        $validator = Validator::make(
            $request->all(),
            [
                'confirm_name' => ['required', 'in:' . $tournament->name],
            ],
            [
                'confirm_name.in' => 'Turniername stimmt nicht überein'
            ]
        );

        /**
         * Wenn Validierung fehlschlägt:
         * - Bei AJAX → JSON Response mit Fehler
         * - Bei normalem Request → Redirect zurück mit Fehler
         */
        if ($validator->fails()) {

            // AJAX Request
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Turniername stimmt nicht überein'
                ], 422);
            }

            // Normaler Request
            return back()
                ->withErrors($validator, 'reset')
                ->withInput();
        }

        /**
         * Datenbank-Transaktion:
         * Alle Änderungen werden atomar durchgeführt
         * Falls etwas fehlschlägt → alles wird zurückgerollt
         */
        DB::transaction(function () use ($tournament) {

            /**
             * Löscht alle Spiele des Turniers
             */
            $tournament->games()->delete();

            /**
             * Löscht alle Gruppen des Turniers
             */
            $tournament->groups()->delete();

            /**
             * Setzt Turnierstatus zurück auf "draft"
             * (Turnier ist wieder im Startzustand)
             */
            $tournament->update([
                'status' => 'draft'
            ]);
        });

        /**
         * AJAX Response
         *
         * Wird verwendet wenn Reset per JS ausgelöst wurde
         * "fullReload" sorgt für komplettes Neuladen der Seite
         */
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'fullReload' => true
            ]);
        }

        /**
         * Normaler Redirect
         *
         * Wird verwendet wenn Reset per Formular ausgelöst wurde
         */
        return redirect()
            ->route('tournaments.show', $tournament)
            ->with('success', 'Turnier wurde zurückgesetzt.');
    }
    /*
|--------------------------------------------------------------------------
| Einzelnes Spiel zurücksetzen
|--------------------------------------------------------------------------
|
| Setzt ein einzelnes Spiel zurück, inklusive möglicher
| Auswirkungen auf nachfolgende KO-Spiele.
|
| Ablauf:
|
| 1. Sicherheitsprüfung
| 2. Spielscore löschen
| 3. Gewinner entfernen
| 4. Falls KO-Spiel → nächsten Gegner entfernen
|
| Beispiel:
|
| Viertelfinale
| A gewinnt gegen B
|
| → Halbfinale bekommt Spieler A
|
| Wenn das Viertelfinale zurückgesetzt wird,
| muss Spieler A auch aus dem Halbfinale entfernt werden.
|
*/

    public function resetGame(Request $request, Game $game)
    {
        /*
    |--------------------------------------------------------------------------
    | Turnier ermitteln + Sicherheitsprüfung
    |--------------------------------------------------------------------------
    |
    | Jedes Spiel gehört zu genau einem Turnier.
    | Nur der Besitzer darf Änderungen durchführen.
    |
    */
        $tournament = $game->tournament;

        $this->authorizeTournament($tournament);


        /*
    |--------------------------------------------------------------------------
    | Datenbank-Transaktion
    |--------------------------------------------------------------------------
    |
    | Stellt sicher, dass:
    | - Spiel + Folge-Spiel konsistent bleiben
    | - bei Fehlern alles zurückgerollt wird
    |
    */
        DB::transaction(function () use ($game, $tournament) {

            /*
        |--------------------------------------------------------------------------
        | Alten Gewinner merken
        |--------------------------------------------------------------------------
        |
        | Wird benötigt, um ihn ggf. aus dem nächsten KO-Spiel
        | wieder zu entfernen.
        |
        */
            $oldWinnerId = $game->winner_id;


            /*
        |--------------------------------------------------------------------------
        | Spiel zurücksetzen
        |--------------------------------------------------------------------------
        |
        | Entfernt:
        | - Scores
        | - Gewinner
        | - Rest (BO1)
        |
        */
            $game->update([
                'player1_score' => null,
                'player2_score' => null,
                'winner_id'     => null,
                'winning_rest'  => null,
            ]);


            /*
        |--------------------------------------------------------------------------
        | KO-Folge-Spiel korrigieren (falls notwendig)
        |--------------------------------------------------------------------------
        |
        | Nur relevant wenn:
        | - KEIN Gruppenspiel (group_id === null)
        | - KO-Runde vorhanden
        |
        */
            if ($game->group_id === null && $game->round !== null) {

                /*
            |--------------------------------------------------------------------------
            | Nächstes Spiel bestimmen
            |--------------------------------------------------------------------------
            |
            | Beispiel:
            | Viertelfinale Spiel 1 → Halbfinale Spiel 1
            |
            */
                $nextRound = $game->round + 1;
                $nextPosition = (int) ceil($game->position / 2);


                /*
            |--------------------------------------------------------------------------
            | Folge-Spiel laden
            |--------------------------------------------------------------------------
            */
                $nextGame = Game::where('tournament_id', $tournament->id)
                    ->where('round', $nextRound)
                    ->where('position', $nextPosition)
                    ->first();


                /*
            |--------------------------------------------------------------------------
            | Gewinner aus Folge-Spiel entfernen
            |--------------------------------------------------------------------------
            */
                if ($nextGame && $oldWinnerId) {

                    if ($nextGame->player1_id === $oldWinnerId) {
                        $nextGame->player1_id = null;
                    }

                    if ($nextGame->player2_id === $oldWinnerId) {
                        $nextGame->player2_id = null;
                    }


                    /*
                |--------------------------------------------------------------------------
                | Falls ein Spieler fehlt → Ergebnis löschen
                |--------------------------------------------------------------------------
                |
                | Ein KO-Spiel ohne beide Spieler darf kein Ergebnis haben.
                |
                */
                    if (!$nextGame->player1_id || !$nextGame->player2_id) {

                        $nextGame->player1_score = null;
                        $nextGame->player2_score = null;
                        $nextGame->winner_id     = null;
                        $nextGame->winning_rest  = null;
                    }

                    $nextGame->save();
                }
            }

            /*
|--------------------------------------------------------------------------
| 🥉 Spiel um Platz 3 ebenfalls bereinigen
|--------------------------------------------------------------------------
*/
            $thirdPlaceGame = Game::where('tournament_id', $tournament->id)
                ->where('is_third_place', true)
                ->first();

            if ($thirdPlaceGame && $oldWinnerId) {

                // 👉 LOSER bestimmen (nicht Winner!)
                $loserId = ($oldWinnerId === $game->player1_id)
                    ? $game->player2_id
                    : $game->player1_id;

                if ($thirdPlaceGame->player1_id === $loserId) {
                    $thirdPlaceGame->player1_id = null;
                }

                if ($thirdPlaceGame->player2_id === $loserId) {
                    $thirdPlaceGame->player2_id = null;
                }

                // Ergebnis zurücksetzen wenn nötig
                if (!$thirdPlaceGame->player1_id || !$thirdPlaceGame->player2_id) {
                    $thirdPlaceGame->player1_score = null;
                    $thirdPlaceGame->player2_score = null;
                    $thirdPlaceGame->winner_id     = null;
                    $thirdPlaceGame->winning_rest  = null;
                }

                $thirdPlaceGame->save();
            }
        });


        /*
    |--------------------------------------------------------------------------
    | JSON Response für AJAX
    |--------------------------------------------------------------------------
    |
    | Wird benötigt für:
    | - reloadGame(game_id)
    | - Live UI Updates ohne Reload
    |
    */
        if ($request->expectsJson()) {

            return response()->json([
                'success' => true,
                'game_id' => $game->id,
            ]);
        }


        /*
    |--------------------------------------------------------------------------
    | Standard Redirect (Fallback)
    |--------------------------------------------------------------------------
    |
    | Wird verwendet wenn kein AJAX Request vorliegt.
    |
    */
        return back()->with('success', 'Spiel erfolgreich zurückgesetzt.');
    }
    /*
|--------------------------------------------------------------------------
| KO PHASE ZURÜCKSETZEN
|--------------------------------------------------------------------------
|
| Entfernt alle Spieler und Ergebnisse aus der KO Phase,
| lässt aber die Struktur des Brackets bestehen.
|
| Dadurch bleiben:
|
| - round
| - position
| - player1_source
| - player2_source
|
| erhalten.
|
*/

    public function resetKo(Request $request, Tournament $tournament)
    {
        /*
    |--------------------------------------------------------------------------
    | 🔒 Nur für Group-KO erlaubt
    |--------------------------------------------------------------------------
    |
    | Reine KO-Turniere haben keine Gruppenphase,
    | daher macht ein KO-Reset hier keinen Sinn.
    |
    */
        if ($tournament->mode !== 'group_ko') {

            return back()->with(
                'error',
                'KO-Reset ist nur bei Turnieren mit Gruppenphase möglich.'
            );
        }


        /*
    |--------------------------------------------------------------------------
    | Sicherheitsprüfung
    |--------------------------------------------------------------------------
    */
        $this->authorizeTournament($tournament);


        /*
    |--------------------------------------------------------------------------
    | Bestätigungsname validieren (Willensbestätigung)
    |--------------------------------------------------------------------------
    |
    | Gleiche Logik wie beim Komplett-Reset:
    | Der Benutzer muss den Turniernamen korrekt eingeben.
    |
    | WICHTIG:
    | Eigener Error-Bag ("resetKo"), damit:
    | - nur das KO-Modal wieder geöffnet wird
    | - keine Kollision mit anderen Fehlern entsteht
    |
    */
        $validator = Validator::make(
            $request->all(),
            [
                'confirm_name' => ['required', 'in:' . $tournament->name],
            ],
            [
                'confirm_name.in' => 'Turniername stimmt nicht überein'
            ]
        );

        if ($validator->fails()) {
            return back()
                ->withErrors($validator, 'resetKo') // 🔥 eigener Error-Bag
                ->withInput();
        }


        /*
    |--------------------------------------------------------------------------
    | KO-Reset innerhalb einer Transaktion
    |--------------------------------------------------------------------------
    |
    | Es werden NUR KO-Spiele zurückgesetzt:
    | - Gruppenspiele bleiben unverändert
    | - Bracket-Struktur bleibt bestehen
    |
    */
        DB::transaction(function () use ($tournament) {

            /*
        |--------------------------------------------------------------------------
        | Alle KO Spiele laden
        |--------------------------------------------------------------------------
        |
        | KO Spiele erkennt man daran,
        | dass sie keiner Gruppe zugeordnet sind (group_id = null)
        |
        */
            $games = $tournament->games()
                ->whereNull('group_id')
                ->get();


            /*
        |--------------------------------------------------------------------------
        | Spiele zurücksetzen
        |--------------------------------------------------------------------------
        |
        | Entfernt:
        | - Spielerzuweisungen
        | - Scores
        | - Gewinner
        |
        */
            foreach ($games as $game) {

                $game->update([
                    'player1_id' => null,
                    'player2_id' => null,
                    'player1_score' => null,
                    'player2_score' => null,
                    'winner_id' => null,
                ]);
            }


            /*
        |--------------------------------------------------------------------------
        | Turnierstatus zurück auf Gruppenphase
        |--------------------------------------------------------------------------
        |
        | Nach dem Reset kann die KO-Phase erneut gestartet werden.
        |
        */
            $tournament->update([
                'status' => 'group_running'
            ]);
        });


        /*
    |--------------------------------------------------------------------------
    | Redirect + Feedback
    |--------------------------------------------------------------------------
    */
        return back()->with('success', 'KO Phase zurückgesetzt.');
    }
    /*
|--------------------------------------------------------------------------
| TURNIER STATUS
|--------------------------------------------------------------------------
*/

    /*
|--------------------------------------------------------------------------
| Abgeschlossenes Turnier wieder öffnen
|--------------------------------------------------------------------------
|
| Diese Methode ermöglicht es, ein bereits abgeschlossenes Turnier
| wieder in einen laufenden Zustand zu versetzen.
|
| Beispiel:
|
| Finale wurde gespielt → Turnierstatus = finished
|
| Danach wird ein Fehler entdeckt oder ein Ergebnis muss korrigiert
| werden. Mit dieser Methode kann das Turnier wieder geöffnet werden.
|
| Ablauf:
|
| 1. Sicherheitsprüfung (Turnierbesitzer)
| 2. Prüfen ob Turnier tatsächlich abgeschlossen ist
| 3. Neuen Status bestimmen
| 4. Turniersieger zurücksetzen
| 5. Status aktualisieren
|
| Statuslogik:
|
| Wenn KO-Spiele existieren
| → Status = ko_running
|
| Wenn keine KO-Spiele existieren
| → Status = group_running
|
*/

    public function reopen(Tournament $tournament)
    {
        /*
    |--------------------------------------------------------------------------
    | Sicherheitsprüfung
    |--------------------------------------------------------------------------
    */

        $this->authorizeTournament($tournament);


        /*
    |--------------------------------------------------------------------------
    | Prüfen ob Turnier abgeschlossen ist
    |--------------------------------------------------------------------------
    */

        if ($tournament->status !== 'finished') {

            return back()->with(
                'error',
                'Nur abgeschlossene Turniere können wieder geöffnet werden.'
            );
        }


        /*
    |--------------------------------------------------------------------------
    | Neuen Status bestimmen
    |--------------------------------------------------------------------------
    |
    | Falls KO-Spiele existieren,
    | wird das Turnier in die KO-Phase zurückgesetzt.
    |
    | Andernfalls zurück zur Gruppenphase.
    |
    */

        if ($tournament->games()->whereNull('group_id')->exists()) {

            $newStatus = 'ko_running';
        } else {

            $newStatus = 'group_running';
        }


        /*
    |--------------------------------------------------------------------------
    | Turnierstatus aktualisieren
    |--------------------------------------------------------------------------
    */

        $tournament->update([
            'status' => $newStatus,
        ]);


        /*
    |--------------------------------------------------------------------------
    | Turniersieger zurücksetzen
    |--------------------------------------------------------------------------
    */

        $tournament->update([
            'winner_id' => null
        ]);


        /*
    |--------------------------------------------------------------------------
    | Zurück zur Turnierseite
    |--------------------------------------------------------------------------
    */

        return back()->with(
            'success',
            'Turnier wurde wieder geöffnet.'
        );
    }
    /*
|--------------------------------------------------------------------------
| SECURITY
|--------------------------------------------------------------------------
*/

    /*
|--------------------------------------------------------------------------
| Turnierzugriff autorisieren
|--------------------------------------------------------------------------
|
| Diese Methode stellt sicher, dass nur der Besitzer eines Turniers
| administrative Aktionen durchführen darf.
|
| Sie wird in vielen Controller-Methoden aufgerufen, z.B.:
|
| - show()
| - start()
| - startKo()
| - resetKo()
| - updateScore()
|
| Ablauf:
|
| 1. Prüfen ob das Turnier dem aktuell eingeloggten Benutzer gehört
| 2. Falls nicht → Zugriff verweigern (HTTP 403)
|
| Beispiel:
|
| User A erstellt Turnier
|
| User B versucht über URL:
| /tournaments/5
|
| → Zugriff wird mit 403 Forbidden blockiert
|
| Vorteil dieser Methode:
|
| Sicherheitslogik ist zentralisiert und muss nicht
| in jeder Controller-Methode dupliziert werden.
|
*/

    private function authorizeTournament(Tournament $tournament)
    {
        /*
    |--------------------------------------------------------------------------
    | Prüfen ob der eingeloggte Benutzer der Besitzer ist
    |--------------------------------------------------------------------------
    */

        if ($tournament->user_id !== auth()->id()) {

            /*
        |--------------------------------------------------------------
        | Zugriff verweigern
        |--------------------------------------------------------------
        |
        | HTTP Status 403 = Forbidden
        |
        */

            abort(403);
        }
    }
    /*
|--------------------------------------------------------------------------
| GROUP SOURCE AUFLÖSEN
|--------------------------------------------------------------------------
|
| Wandelt Quellen wie "A1", "B2", "C3" in echte Spieler um.
|
| Beispiel:
| A1 -> 1. Platz Gruppe A
|
*/

    private function resolveGroupSource(string $source, array $tables)
    {
        /*
    |--------------------------------------------------------------------------
    | Erwartetes Format prüfen (A1, B2, C3 ...)
    |--------------------------------------------------------------------------
    */

        if (preg_match('/([A-Z])(\d+)/', $source, $match)) {

            $groupName = $match[1];
            $place = (int) $match[2] - 1;

            if (!isset($tables[$groupName][$place])) {
                return null;
            }

            return $tables[$groupName][$place]['player']->id;
        }

        return null;
    }

    /**
     * ================================================================
     * Lucky Loser Turnier erstellen
     * ================================================================
     *
     * Erstellt ein separates Turnier mit allen Spielern, die sich
     * NICHT für die KO-Phase qualifiziert haben.
     *
     * Konzept:
     * - Kein "Best-of-Rest" Ranking
     * - Stattdessen: Second-Chance Bracket
     *
     * Ablauf:
     * 1. Qualifizierte Spieler aus Gruppen entfernen
     * 2. Restspieler sammeln
     * 3. Spieler zufällig mischen (shuffle)
     * 4. Neues Turnier erstellen (status = draft)
     * 5. Spieler KOPIEREN (nicht verschieben!)
     *
     * WICHTIG:
     * - Spieler werden dupliziert, nicht verschoben
     * - Hauptturnier bleibt unverändert
     * - KO wird NICHT automatisch gestartet
     *
     * @param Tournament $tournament
     * @param array $tables
     * @return void
     */
    private function createLuckyLoserTournament(Tournament $tournament, array $tables)
    {
        /*
    |--------------------------------------------------------------------------
    | Qualifizierte Spieler bestimmen
    |--------------------------------------------------------------------------
    */
        $qualifiedIds = collect();

        foreach ($tables as $groupName => $table) {
            $qualifiedIds = $qualifiedIds->merge(
                collect($table)
                    ->take($tournament->group_advance_count)
                    ->pluck('player.id')
            );
        }

        /*
    |--------------------------------------------------------------------------
    | Restspieler = Lucky Loser Pool
    |--------------------------------------------------------------------------
    */
        $losers = $tournament->players
            ->whereNotIn('id', $qualifiedIds)
            ->shuffle()
            ->values();

        if ($losers->count() < 2) {
            return;
        }

        /*
    |--------------------------------------------------------------------------
    | 🔥 Eindeutig das Lucky Turnier holen
    |--------------------------------------------------------------------------
    */
        $lucky = Tournament::firstOrCreate(
            [
                'parent_id' => $tournament->id,
                'type' => 'lucky_loser',
            ],
            [
                'name' => $tournament->name . ' - Lucky Loser',
                'user_id' => $tournament->user_id,
                'mode' => 'ko',
                'status' => 'draft',
                'has_third_place' => true,
            ]
        );

        $lucky->update([
            'has_third_place' => true,
        ]);

        /*
    |--------------------------------------------------------------------------
    | 🔄 SPIELER SYNC (kein blindes delete mehr)
    |--------------------------------------------------------------------------
    */
        $lucky->players()->delete();

        foreach ($losers as $player) {
            $lucky->players()->create([
                'name' => $player->name,
                'seed' => $player->seed,
            ]);
        }

        /*
    |--------------------------------------------------------------------------
    | 🔄 KO BRACKET RESET + NEU GENERIEREN
    |--------------------------------------------------------------------------
    */
        $lucky->games()->delete();
        $lucky->groups()->delete();

        $players = $lucky->players()->get()->values();

        app(\App\Services\Knockout\KnockoutGenerator::class)
            ->generateDirectBracket($lucky, $players);

        $lucky->update([
            'status' => 'ko_running',
        ]);

        /*
    |--------------------------------------------------------------------------
    | TV Eintrag nur einmal
    |--------------------------------------------------------------------------
    */
        if (!$lucky->tvTournament) {
            \App\Models\TvTournament::create([
                'user_id' => $lucky->user_id,
                'tournament_id' => $lucky->id,
                'position' => 999
            ]);
        }
    }
    /*
|--------------------------------------------------------------------------
| ARCHIVE
|--------------------------------------------------------------------------
*/

    public function archive(Tournament $tournament)
    {
        // 🔒 Sicherheit!
        $this->authorizeTournament($tournament);

        $tournament->update([
            'status' => 'archived'
        ]);

        return redirect()
            ->route('tournaments.index')
            ->with('success', 'Turnier wurde archiviert');
    }

    /*
|--------------------------------------------------------------------------
| ARCHIV LISTE
|--------------------------------------------------------------------------
*/
    public function archiveList()
    {
        $tournaments = Tournament::where('status', 'archived')
            ->where('user_id', auth()->id())
            ->latest()
            ->get();

        return view('tournaments.archive', compact('tournaments'));
    }


    /*
|--------------------------------------------------------------------------
| RESTORE
|--------------------------------------------------------------------------
*/
    public function restore(Tournament $tournament)
    {
        // 🔒 Sicherheit
        $this->authorizeTournament($tournament);

        $tournament->update([
            'status' => 'finished' // oder 'draft' je nach gewünschtem Verhalten
        ]);

        return redirect()
            ->route('tournaments.archive')
            ->with('success', 'Turnier wiederhergestellt');
    }
}
