<?php

use App\Jobs\CekJatuhTempoDeposito;
use App\Jobs\HitungBungaBulanan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Jadwal
|--------------------------------------------------------------------------
|
| Di server cukup satu cron: * * * * * cd /path/app && php artisan schedule:run
| Job antre diproses oleh `php artisan queue:work` (lihat README).
|
*/

Schedule::command('backup:clean')->dailyAt('01:00')->onOneServer();
Schedule::command('backup:run --only-db')->dailyAt('01:30')->onOneServer();
Schedule::command('backup:monitor')->dailyAt('08:00');

Schedule::job(new CekJatuhTempoDeposito)->dailyAt('00:30')->onOneServer();
Schedule::job(HitungBungaBulanan::untukBulanLalu())->monthlyOn(1, '02:00')->onOneServer();

Schedule::command('queue:prune-failed --hours=720')->weekly();
