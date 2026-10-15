<?php

namespace App\Providers;

use App\Services\CalculJoursOuvres;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Le calendrier des jours ouvrés lit les paramètres et les jours fériés :
        // une seule instance par requête suffit.
        $this->app->scoped(CalculJoursOuvres::class, fn () => new CalculJoursOuvres);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
