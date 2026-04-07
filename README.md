# Dart Tournament Manager

Ein webbasiertes Turnierverwaltungssystem fuer Dart-Turniere mit Gruppenphase, KO-Baum, TV-Ansicht und oeffentlicher Follow-Seite.

## Features

- Spielerverwaltung einzeln oder per Bulk-Import
- KO-Turniere und Gruppenphase mit anschliessender KO-Runde
- Automatische Bracket-Generierung inklusive BYEs
- Optionales Spiel um Platz 3
- Optionales Lucky-Loser-Turnier als eigenes Second-Chance-Bracket
- Live-Ergebniserfassung ohne kompletten Seiten-Reload
- TV-Rotation fuer mehrere aktive Turniere
- Oeffentliche Follow-Seiten mit QR-Code-Links

## Technischer Stack

- PHP 8.2+
- Laravel 12
- MariaDB oder MySQL fuer den Produktivbetrieb
- Vite fuer Frontend-Builds
- Tailwind CSS und Alpine.js im internen Backend

## Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link
```

Danach kann die lokale Entwicklungsumgebung mit folgendem Kommando gestartet werden:

```bash
composer run dev
```

## Konfiguration

Wichtige Umgebungsvariablen in [.env.example](/c:/Users/langen/dartmanager/.env.example):

- `REGISTRATION_ENABLED`: Schaltet die Benutzerregistrierung ein oder aus
- `AUTOSIM_ENABLED`: Aktiviert die Auto-Simulation im Frontend
- `DB_*`: Datenbankverbindung fuer den lokalen oder produktiven Betrieb
- `APP_LOCALE` und `APP_FAKER_LOCALE`: Standardsprache und Faker-Locale

## Nutzung

1. Turnier anlegen
2. Spieler erfassen
3. Auslosung starten
4. Gruppen- oder KO-Phase starten
5. Ergebnisse erfassen
6. Optional TV-Ansicht oder Follow-Link freigeben

## Routen und Ansichten

Die wichtigsten Oberflaechen sind:

- `/tournaments`: interne Verwaltung aller aktiven Turniere
- `/tournaments/archive`: Archivansicht
- `/admin/tv`: Auswahl der Turniere fuer die TV-Rotation
- `/tv`: geschuetzte TV-Rotation fuer angemeldete Benutzer
- `/tv/{public_id}`: geschuetzte Einzelansicht eines TV-Turniers
- `/follow/{public_id}`: oeffentliche Follow-Seite fuer Zuschauer
- `/follow/{public_id}/data`: JSON-Polling-Endpoint fuer die Follow-Seite

## Wichtige Dateien

Ein paar Dateien waren bisher funktional vorhanden, aber kaum oder gar nicht dokumentiert:

- [app/Http/Controllers/PublicController.php](/c:/Users/langen/dartmanager/app/Http/Controllers/PublicController.php): baut die oeffentliche Follow-Ansicht und deren Live-Daten auf
- [app/Http/Controllers/TvController.php](/c:/Users/langen/dartmanager/app/Http/Controllers/TvController.php): verwaltet TV-Auswahl, Rotation und Einzelansichten
- [resources/views/tv/rotation.blade.php](/c:/Users/langen/dartmanager/resources/views/tv/rotation.blade.php): rotiert zwischen Overview und TV-Frames
- [resources/views/public/follow.blade.php](/c:/Users/langen/dartmanager/resources/views/public/follow.blade.php): Zuschaueransicht mit Polling, Filter und Sieg-Overlay
- [public/css/follow.css](/c:/Users/langen/dartmanager/public/css/follow.css): zusaetzliche Styles fuer die oeffentliche Follow-Seite
- [app/Services/Knockout/KnockoutGenerator.php](/c:/Users/langen/dartmanager/app/Services/Knockout/KnockoutGenerator.php): erzeugt Brackets und verarbeitet BYEs
- [app/Services/Tournament/TournamentResetService.php](/c:/Users/langen/dartmanager/app/Services/Tournament/TournamentResetService.php): setzt Turniere in einen konsistenten Ausgangszustand zurueck

Die ungenutzte Datei `public/js/follow.js` wurde entfernt, weil die Follow-Logik inzwischen direkt in der Blade-Ansicht gepflegt wird.

## Tests

Die Tests laufen jetzt ohne vorausgesetzte lokale MariaDB-Testdatenbank mit SQLite im Speicher:

```bash
php artisan test
```

Falls du produktionsnahe Datenbanktests gegen MariaDB fahren willst, kannst du die Werte in [phpunit.xml](/c:/Users/langen/dartmanager/phpunit.xml) oder in einer separaten Test-Env wieder anpassen.

## Build

```bash
npm run build
```

Hinweis: In stark eingeschraenkten Sandbox- oder CI-Umgebungen kann `vite build` an Prozessrechten fuer `esbuild` scheitern. Im normalen lokalen Setup sollte der Build regulär laufen.

## Lizenz

Dieses Projekt steht unter der GNU GPL v3. Details stehen in [LICENSE](/c:/Users/langen/dartmanager/LICENSE).
