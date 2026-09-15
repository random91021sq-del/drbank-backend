<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('appointment-holds:cleanup', function () {
    $deleted = DB::table('appointment_slot_holds')
        ->where('expires_at', '<=', now('America/Lima'))
        ->delete();

    $this->info("Reservas temporales eliminadas: {$deleted}");
})->purpose('Eliminar reservas temporales de citas vencidas');

Schedule::command('appointment-holds:cleanup')
    ->hourly()
    ->timezone('America/Lima')
    ->withoutOverlapping();

Schedule::command('student:plan')
    ->weeklyOn(1, '00:00')
    ->timezone('America/Lima')
    ->withoutOverlapping();

Schedule::command('student:recommendation')
    ->everyThreeHours()
    ->timezone('America/Lima')
    ->withoutOverlapping();
