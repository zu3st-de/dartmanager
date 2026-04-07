<?php

namespace App\Providers;

use App\Models\Tournament;
use App\Models\TvTournament;
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
            $tvRotationTime = 20;
            $hasTvTournaments = false;

            if (Auth::check()) {
                $activeTournaments = Tournament::query()
                    ->where('user_id', Auth::id())
                    ->active()
                    ->latest()
                    ->get();

                $tvRotationTime = TvTournament::query()
                    ->where('user_id', Auth::id())
                    ->orderBy('position')
                    ->value('rotation_time') ?? 20;

                $hasTvTournaments = TvTournament::query()
                    ->where('user_id', Auth::id())
                    ->exists();
            }

            $view->with('activeTournaments', $activeTournaments);
            $view->with('tvRotationTime', $tvRotationTime);
            $view->with('hasTvTournaments', $hasTvTournaments);
        });
    }
}
