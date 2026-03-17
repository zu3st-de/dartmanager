<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\Game;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Services\KnockoutGenerator;
use App\Services\TournamentEngine;
use App\Services\GroupGenerator;
use App\Services\GroupTableCalculator;

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
 * TournamentEngine      → Gewinner in nächste Runde setzen
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
    | Turniere des aktuellen Users laden
    |--------------------------------------------------------------
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
    | best_of = 3
    |
    */

        $groupBestOf = $tournament->games()
            ->whereNotNull('group_id')
            ->pluck('best_of')
            ->unique()
            ->first() ?? 3;


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
            |--------------------------------------------------------------
            | KO Baum generieren
            |--------------------------------------------------------------
            */

                app(KnockoutGenerator::class)
                    ->generateBracket($tournament);


                /*
            |--------------------------------------------------------------
            | Turnierstatus setzen
            |--------------------------------------------------------------
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
                    'success' => false,
                    'message' => 'Spiel bereits entschieden'
                ]);
            }

            return back();
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

        $game->validateResult(
            $player1Score,
            $player2Score,
            $winningRest
        );


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
    | Sobald ein Gewinner feststeht übernimmt die TournamentEngine:
    |
    | - winner_id setzen
    | - nächsten Gegner bestimmen
    | - Finale erkennen
    | - Turniersieger setzen
    |
    */

        if ($winnerId) {

            app(TournamentEngine::class)
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
                'winner_id' => $game->winner_id,
                'player1_score' => $game->player1_score,
                'player2_score' => $game->player2_score,
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
| Best-of der Gruppenspiele ändern
|--------------------------------------------------------------------------
|
| Diese Methode erlaubt es, die Länge der Gruppenspiele zu ändern.
|
| Beispiel:
|
| Best-of 1
| → First to 1 Leg
|
| Best-of 3
| → First to 2 Legs
|
| Best-of 5
| → First to 3 Legs
|
| Einschränkung:
|
| Sobald bereits ein Gruppenspiel abgeschlossen wurde,
| darf der Best-of Wert nicht mehr geändert werden.
|
| Ablauf:
|
| 1. Prüfen ob bereits Gruppenspiele entschieden wurden
| 2. Neue Best-of Einstellung validieren
| 3. Alle Gruppenspiele aktualisieren
|
| Route:
| POST /tournaments/{tournament}/group-best-of
|
*/

    public function updateGroupBestOf(Request $request, Tournament $tournament)
    {
        /*
    |--------------------------------------------------------------------------
    | Prüfen ob bereits Gruppenspiele Ergebnisse haben
    |--------------------------------------------------------------------------
    |
    | Wenn bereits Ergebnisse existieren,
    | darf Best-of nicht mehr geändert werden.
    |
    */

        $groupHasResults = $tournament->games()
            ->whereNotNull('group_id')
            ->whereNotNull('winner_id')
            ->exists();


        if ($groupHasResults) {

            return back()->with(
                'error',
                'Best Of kann nicht mehr geändert werden.'
            );
        }


        /*
    |--------------------------------------------------------------------------
    | Neue Best-of Einstellung validieren
    |--------------------------------------------------------------------------
    */

        $request->validate([
            'group_best_of' => 'required|in:1,3,5,7'
        ]);


        /*
    |--------------------------------------------------------------------------
    | Best-of für alle Gruppenspiele aktualisieren
    |--------------------------------------------------------------------------
    */

        $tournament->games()
            ->whereNotNull('group_id')
            ->update([
                'best_of' => $request->group_best_of
            ]);


        /*
    |--------------------------------------------------------------------------
    | Zurück zur Turnierseite
    |--------------------------------------------------------------------------
    */

        return back();
    }/*
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

    /*
|--------------------------------------------------------------------------
| KO PHASE STARTEN
|--------------------------------------------------------------------------
|
| Diese Methode wird aufgerufen, nachdem die Gruppenphase abgeschlossen
| wurde. Der KO-Baum existiert bereits (Placeholders wurden beim
| Turnierstart erstellt).
|
| Aufgabe dieser Methode:
|
| 1. Gruppentabellen berechnen
| 2. player1_source / player2_source auswerten (z.B. A1, B4)
| 3. entsprechende Spieler einsetzen
| 4. KO-Phase starten
|
| Beispiel:
|
| player1_source = A1
| player2_source = B4
|
| bedeutet:
| 1. Platz Gruppe A
| vs
| 4. Platz Gruppe B
|
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

    /*
|--------------------------------------------------------------------------
| Komplettes Turnier zurücksetzen
|--------------------------------------------------------------------------
|
| Setzt ein Turnier vollständig auf den Ausgangszustand zurück.
|
| Das bedeutet:
|
| - Alle Spiele werden gelöscht
| - Alle Gruppen werden gelöscht
| - Der Turnierstatus wird wieder auf "draft" gesetzt
|
| Die Spieler bleiben erhalten, damit das Turnier schnell
| neu gestartet werden kann.
|
| Sicherheitsmechanismus:
|
| Der Benutzer muss zur Bestätigung den exakten Turniernamen
| eingeben.
|
| Ablauf:
|
| 1. Sicherheitsprüfung (Turnierbesitzer)
| 2. Bestätigungsname validieren
| 3. Alle Spiele löschen
| 4. Gruppen löschen
| 5. Turnierstatus zurücksetzen
|
*/

    public function reset(Request $request, Tournament $tournament)
    {
        /*
    |--------------------------------------------------------------------------
    | Sicherheitsprüfung
    |--------------------------------------------------------------------------
    */

        $this->authorizeTournament($tournament);


        /*
    |--------------------------------------------------------------------------
    | Bestätigungsname validieren
    |--------------------------------------------------------------------------
    */

        $request->validate([
            'confirm_name' => ['required', 'in:' . $tournament->name],
        ]);


        /*
    |--------------------------------------------------------------------------
    | Reset innerhalb einer Datenbank-Transaktion
    |--------------------------------------------------------------------------
    */

        DB::transaction(function () use ($tournament) {

            /*
        |--------------------------------------------------------------
        | Alle Spiele löschen
        |--------------------------------------------------------------
        */

            $tournament->games()->delete();


            /*
        |--------------------------------------------------------------
        | Gruppen löschen
        |--------------------------------------------------------------
        */

            $tournament->groups()->delete();


            /*
        |--------------------------------------------------------------
        | Turnierstatus zurücksetzen
        |--------------------------------------------------------------
        */

            $tournament->update([
                'status' => 'draft'
            ]);
        });


        /*
    |--------------------------------------------------------------------------
    | Zurück zur Turnierseite
    |--------------------------------------------------------------------------
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

    public function resetGame(Game $game)
    {
        $tournament = $game->tournament;

        /*
    |--------------------------------------------------------------------------
    | Sicherheitsprüfung
    |--------------------------------------------------------------------------
    */

        $this->authorizeTournament($tournament);


        DB::transaction(function () use ($game, $tournament) {

            $oldWinnerId = $game->winner_id;


            /*
        |--------------------------------------------------------------------------
        | Spiel selbst zurücksetzen
        |--------------------------------------------------------------------------
        */

            $game->update([
                'player1_score' => null,
                'player2_score' => null,
                'winner_id'     => null,
                'winning_rest'  => null,
            ]);


            /*
        |--------------------------------------------------------------------------
        | Wenn KO-Spiel → Folge-Spiel korrigieren
        |--------------------------------------------------------------------------
        */

            if ($game->group_id === null && $game->round !== null) {

                $nextRound = $game->round + 1;
                $nextPosition = (int) ceil($game->position / 2);


                $nextGame = Game::where('tournament_id', $tournament->id)
                    ->where('round', $nextRound)
                    ->where('position', $nextPosition)
                    ->first();


                if ($nextGame && $oldWinnerId) {

                    /*
                |------------------------------------------------------
                | Gewinner aus nächstem Spiel entfernen
                |------------------------------------------------------
                */

                    if ($nextGame->player1_id === $oldWinnerId) {
                        $nextGame->player1_id = null;
                    }

                    if ($nextGame->player2_id === $oldWinnerId) {
                        $nextGame->player2_id = null;
                    }


                    /*
                |------------------------------------------------------
                | Falls Gegner fehlt → Ergebnis löschen
                |------------------------------------------------------
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
        });


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

    public function resetKo(Tournament $tournament)
    {
        $this->authorizeTournament($tournament);

        DB::transaction(function () use ($tournament) {

            /*
        |--------------------------------------------------------------------------
        | Alle KO Spiele laden
        |--------------------------------------------------------------------------
        |
        | KO Spiele haben keine group_id
        |
        */

            $games = $tournament->games()
                ->whereNull('group_id')
                ->get();


            /*
        |--------------------------------------------------------------------------
        | Spiele zurücksetzen
        |--------------------------------------------------------------------------
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
        | Turnierstatus zurücksetzen
        |--------------------------------------------------------------------------
        |
        | Nach einem KO Reset kehrt das Turnier zur
        | Gruppenphase zurück.
        |
        */

            $tournament->update([
                'status' => 'group_running'
            ]);
        });

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
}
