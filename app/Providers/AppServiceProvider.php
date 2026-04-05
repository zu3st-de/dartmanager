<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use App\Models\Tournament;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Aktive Turniere für Navigation global verfügbar machen
        View::composer('layouts.navigation', function ($view) {

            $activeTournaments = Tournament::active()
                ->latest()
                ->get();

            $view->with('activeTournaments', $activeTournaments);
        });
    }
}
