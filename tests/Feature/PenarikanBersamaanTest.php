<?php

use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Process;

/*
| Membuktikan lockForUpdate bekerja: dua proses PHP terpisah menarik 70.000
| dari rekening bersaldo 100.000 pada saat yang sama. Butuh MySQL/MariaDB
| sungguhan (SQLite in-memory tidak mendukung row lock), jadi test ini hanya
| jalan bila TEST_MYSQL_DATABASE diisi, mis.:
|
|   TEST_MYSQL_DATABASE=bank_provita_test vendor/bin/pest --group=mysql
|
| Database tersebut akan dikosongkan (migrate:fresh).
*/

it('dua penarikan bersamaan pada satu rekening tidak membuat saldo negatif', function () {
    $env = [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => 'mysql',
        'DB_DATABASE' => env('TEST_MYSQL_DATABASE'),
        'DB_URL' => '',
        'CACHE_STORE' => 'array',
        'ACTIVITYLOG_ENABLED' => 'false',
    ];

    $php = PHP_BINARY;
    $script = base_path('tests/Support/penarikan.php');

    Process::env($env)->path(base_path())->timeout(120)->run([$php, 'artisan', 'migrate:fresh', '--force'])->throw();
    $rekeningId = trim(Process::env($env)->run([$php, $script, 'setup', '100000'])->throw()->output());

    $results = Process::pool(fn (Pool $pool) => [
        $pool->env($env)->command([$php, $script, 'tarik', $rekeningId, '70000']),
        $pool->env($env)->command([$php, $script, 'tarik', $rekeningId, '70000']),
    ])->start()->wait();

    $outputs = $results->collect()->map(fn ($result) => trim($result->throw()->output()))->sort()->values()->all();

    expect($outputs)->toBe(['DITOLAK', 'OK']);

    [$cache, $hitung] = explode('|', trim(Process::env($env)->run([$php, $script, 'saldo', $rekeningId])->throw()->output()));

    expect($cache)->toBe('30000.00')->and($hitung)->toBe('30000.00');
})->group('mysql')->skip(fn () => blank(env('TEST_MYSQL_DATABASE')), 'Set TEST_MYSQL_DATABASE untuk menjalankan test row lock di MySQL.');
