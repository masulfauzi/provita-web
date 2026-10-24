<?php

namespace App\Http\Modules\Akuntansi\Console;

use App\Http\Modules\Akuntansi\Services\JurnalService;
use App\Http\Modules\Transaksi\Models\Transaksi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Membuatkan jurnal untuk transaksi tabungan yang belum punya jurnal
 * (data sebelum modul Akuntansi). Aman dijalankan berulang kali.
 */
class JurnalTransaksi extends Command
{
    protected $signature = 'akuntansi:jurnal-transaksi';

    protected $description = 'Membuat jurnal untuk transaksi tabungan yang belum dijurnal';

    public function handle(JurnalService $jurnal): int
    {
        $jumlah = 0;

        Transaksi::query()
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))->from('jurnal')->whereColumn('jurnal.transaksi_id', 'transaksi.id'))
            ->orderBy('tanggal')
            ->orderBy('created_at')
            ->orderBy('urut')
            ->each(function (Transaksi $transaksi) use ($jurnal, &$jumlah) {
                DB::transaction(fn () => $jurnal->dariTransaksi($transaksi));
                $jumlah++;
            });

        $this->components->info("{$jumlah} transaksi dijurnal.");

        return self::SUCCESS;
    }
}
