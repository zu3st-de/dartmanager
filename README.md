# 🎯 Dart Tournament Manager

Ein webbasiertes Turnierverwaltungssystem für Dart-Turniere mit Unterstützung für Gruppen- und KO-Phasen.

---

## ✨ Features

* 🧍 Spielerverwaltung (manuell & Bulk-Import)
* 🎲 Zufällige Auslosung (Seeding)
* 👥 Gruppenphase mit automatischer Tabellenberechnung
* 🏆 KO-System mit automatischer Weitergabe der Gewinner
* 🥉 Optionales Spiel um Platz 3
* ⚡ Live-Ergebnis-Eingabe (AJAX)
* 🔄 Automatische Aktualisierung von Folge-Spielen
* 🧠 Intelligente Turnierlogik (BYEs, KO-Baum, etc.)

---

## 🧱 Unterstützte Turniermodi

### 1. KO-Turnier

* Direktes Ausscheidungssystem
* Automatische BYE-Verteilung bei ungerader Spielerzahl

### 2. Gruppen + KO

* Gruppenphase (Round Robin)
* Frei definierbare Anzahl Gruppen & Aufsteiger
* Automatischer Übergang in KO-Phase

---

## ⚙️ Installation

### Voraussetzungen

* PHP >= 8.1
* Composer
* Laravel
* MySQL / MariaDB

### Setup

```bash
git clone https://github.com/DEIN_USERNAME/dart-tournament-manager.git

cd dart-tournament-manager

composer install
cp .env.example .env
php artisan key:generate
```

### Datenbank

```bash
php artisan migrate
```

### Start

```bash
php artisan serve
```

---

## 🕹️ Nutzung

1. Turnier erstellen
2. Spieler hinzufügen (einzeln oder per Liste)
3. Auslosung durchführen
4. Turnier starten
5. Ergebnisse eintragen
6. KO-Phase automatisch generieren

---

## 🧠 Architektur (vereinfacht)

### Controller

* Verarbeitet HTTP Requests
* Übergibt Logik an Services

### Services

* `GroupGenerator` → erstellt Gruppen
* `KnockoutGenerator` → erstellt KO-Baum
* `KnockoutAdvancer` → verarbeitet Spielverläufe

### Models

* `Tournament`
* `Game`
* `Player`
* `Group`

---

## 🔄 Spiel-Logik

* Ergebnisse werden serverseitig verarbeitet
* Gewinner werden automatisch ins nächste Spiel gesetzt
* Änderungen propagieren durch den gesamten KO-Baum
* Spiel um Platz 3 wird automatisch befüllt (optional)

---

## 📡 Live-Updates

* Ergebnisse werden per AJAX gespeichert
* Nur betroffene Spiele werden aktualisiert
* Kein vollständiger Seiten-Reload notwendig

---

## 🔒 Sicherheit

* Zugriff nur für Turnierbesitzer
* Validierung aller Eingaben
* Transaktionen für kritische Operationen

---

## 📜 Lizenz

Dieses Projekt ist unter der **GNU General Public License v3.0 (GPL-3.0)** veröffentlicht.

Das bedeutet:

* ✅ Nutzung erlaubt
* ✅ Veränderung erlaubt
* ✅ Weitergabe erlaubt
* ❗ Änderungen müssen ebenfalls unter GPL veröffentlicht werden

---

## 🙌 Mitwirken

Pull Requests sind willkommen!

Für größere Änderungen bitte vorher ein Issue erstellen.

---

## 📌 Roadmap (Ideen)

* [ ] WebSockets / Live-Multiplayer
* [ ] UI/UX Verbesserungen
* [ ] Statistiken & Spielerhistorie
* [ ] API für externe Apps
* [ ] Mobile Optimierung

---

## ❤️ Credits

Entwickelt mit Laravel und viel Kaffee ☕
