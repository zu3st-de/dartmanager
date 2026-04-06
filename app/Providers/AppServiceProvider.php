<?php

namespace App\Providers;

use App\Models\Tournament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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
            $activeTournaments = collect();

            if (Auth::check()) {
                $activeTournaments = Tournament::query()
                    ->where('user_id', Auth::id())
                    ->active()
                    ->latest()
                    ->get();
            }

            $view->with('activeTournaments', $activeTournaments);
        });
    }
}
