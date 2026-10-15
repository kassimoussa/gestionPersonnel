<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Congés : accumulation mensuelle des soldes
|--------------------------------------------------------------------------
|
| Le 1er de chaque mois, chaque employé actif reçoit les jours de congé
| annuel définis dans config('personnel.conges.jours_acquis_par_mois').
| La commande est idempotente (un mois n'est crédité qu'une fois).
|
| Test manuel : docker compose exec app php artisan conges:accumuler-soldes
| Le scheduler lui-même doit tourner en continu (schedule:work ou cron).
|
*/
Schedule::command('conges:accumuler-soldes')->monthly()->withoutOverlapping();
