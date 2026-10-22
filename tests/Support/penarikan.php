<?php

/**
 * Skrip bantu untuk tests/Feature/PenarikanBersamaanTest.php: dijalankan sebagai
 * proses PHP terpisah agar dua penarikan benar-benar berjalan bersamaan.
 *
 *   php tests/Support/penarikan.php setup <saldo>
 *   php tests/Support/penarikan.php tarik <rekening_id> <nominal>
 *   php tests/Support/penarikan.php saldo <rekening_id>
 */

use App\Http\Modules\Rekening\Models\Rekening;
use App\Http\Modules\Transaksi\Exceptions\TransaksiDitolakException;
use App\Http\Modules\Transaksi\Services\TransaksiService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$aksi = $argv[1] ?? null;

if ($aksi === 'setup') {
    $rekening = Rekening::create(['nomor' => 'TES-'.uniqid(), 'nama' => 'Uji bersamaan', 'saldo_awal' => $argv[2]]);
    $rekening->forceFill(['saldo' => $argv[2]])->save();
    echo $rekening->id;
} elseif ($aksi === 'tarik') {
    // Tahan 500ms setelah membaca baris rekening, saat lock masih dipegang,
    // supaya proses kedua pasti mencoba membaca rekening yang sama di tengah jalan.
    DB::listen(function ($query) {
        if (str_contains($query->sql, 'from `rekening`')) {
            usleep(500_000);
        }
    });

    try {
        app(TransaksiService::class)->tarik($argv[2], $argv[3]);
        echo 'OK';
    } catch (TransaksiDitolakException) {
        echo 'DITOLAK';
    }
} elseif ($aksi === 'saldo') {
    $rekening = Rekening::findOrFail($argv[2]);
    echo $rekening->saldo.'|'.app(TransaksiService::class)->hitungSaldo($rekening);
}
