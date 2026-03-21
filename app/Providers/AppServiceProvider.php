<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Auth;

/**
 * ================================================================
 * AppServiceProvider
 * ================================================================
 *
 * Verantwortlich für:
 *
 * - globale View-Daten
 * - Bootstrapping von App-weiten Features
 *
 * Hier:
 * → aktive Turniere im Navigationsmenü bereitstellen
 *
 * ================================================================
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * ============================================================
     * Services registrieren
     * ============================================================
     */
    public function register(): void
    {
        //
    }

    /**
     * ============================================================
     * Bootstrapping
     * ============================================================
     */
    public function boot(): void
    {
        /*
        |--------------------------------------------------------------------------
        | View Composer für Navigation
        |--------------------------------------------------------------------------
        |
        | Wird bei jedem Render von layouts.navigation ausgeführt
        |
        | Liefert:
        | - alle aktiven (nicht abgeschlossenen) Turniere des Users
        |
        */

        View::composer('layouts.navigation', function ($view) {

            /*
            |--------------------------------------------------------------------------
            | Kein eingeloggter User → leere Collection
            |--------------------------------------------------------------------------
            */

            if (!Auth::check()) {
                $view->with('activeTournaments', collect());
                return;
            }


            /*
            |--------------------------------------------------------------------------
            | Aktive Turniere laden
            |--------------------------------------------------------------------------
            |
            | Optimierungen:
            | - nur benötigte Felder (select)
            | - sortiert nach Erstellung
            |
            */

            $activeTournaments = Auth::user()
                ->tournaments()
                ->whereNotIn('status', ['archived'])
                ->orderBy('created_at')
                ->get(['id', 'name', 'status']);


            /*
            |--------------------------------------------------------------------------
            | Daten an View übergeben
            |--------------------------------------------------------------------------
            */

            $view->with('activeTournaments', $activeTournaments);
        });
    }
}
