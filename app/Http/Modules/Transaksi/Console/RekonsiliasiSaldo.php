<?php

namespace App\Http\Modules\Transaksi\Console;

use App\Http\Modules\Rekening\Models\Rekening;
use App\Http\Modules\Transaksi\Services\TransaksiService;
use App\Support\Money;
use Illuminate\Console\Command;

/**
 * Membandingkan kolom saldo (cache) dengan saldo awal + mutasi.
 */
class RekonsiliasiSaldo extends Command
{
    protected $signature = 'saldo:rekonsiliasi
        {--fix : Perbaiki kolom saldo yang tidak cocok}
        {--rekening= : Hanya nomor rekening tertentu}';

    protected $description = 'Menghitung ulang saldo rekening dari saldo awal + mutasi transaksi';

    public function handle(TransaksiService $service): int
    {
        $selisih = [];

        Rekening::query()
            ->with('nasabah:id,nama')
            ->when($this->option('rekening'), fn ($q, $nomor) => $q->where('nomor', $nomor))
            ->orderBy('nomor')
            ->chunkById(500, function ($rekenings) use ($service, &$selisih) {
                foreach ($rekenings as $rekening) {
                    $seharusnya = $service->hitungSaldo($rekening);

                    if (Money::compare($seharusnya, $rekening->saldo) === 0) {
                        continue;
                    }

                    $selisih[] = [$rekening->nomor, $rekening->nasabah?->nama, Money::format($rekening->saldo), Money::format($seharusnya)];

                    if ($this->option('fix')) {
                        $tersimpan = $rekening->saldo;
                        $rekening->forceFill(['saldo' => $seharusnya])->saveQuietly();
                        activity('rekening')->performedOn($rekening)->event('rekonsiliasi')
                            ->withProperties(['old' => $tersimpan, 'attributes' => $seharusnya])
                            ->log('Saldo diperbaiki oleh saldo:rekonsiliasi');
                    }
                }
            });

        if ($selisih === []) {
            $this->components->info('Semua saldo rekening cocok.');

            return self::SUCCESS;
        }

        $this->table(['Nomor', 'Nama', 'Saldo tersimpan', 'Seharusnya'], $selisih);

        if ($this->option('fix')) {
            $this->components->warn(count($selisih).' rekening diperbaiki.');

            return self::SUCCESS;
        }

        $this->components->error(count($selisih).' rekening tidak cocok. Jalankan dengan --fix untuk memperbaiki.');

        return self::FAILURE;
    }
}
