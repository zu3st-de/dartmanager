<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Auth;

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
        View::composer('layouts.navigation', function ($view) {

            if (Auth::check()) {

                $activeTournaments = Auth::user()
                    ->tournaments()
                    ->where('status', '!=', 'finished')
                    ->orderBy('created_at')
                    ->select('id', 'name', 'status')
                    ->get();

                $view->with('activeTournaments', $activeTournaments);
            } else {
                $view->with('activeTournaments', collect());
            }
        });
    }
}
