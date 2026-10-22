<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Memproses deposito yang jatuh tempo hari ini.
 * Kerangka saja; logika diisi di issue modul Rekening/Deposito.
 */
class CekJatuhTempoDeposito implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public ?string $tanggal = null)
    {
        $this->tanggal ??= today()->toDateString();
    }

    public function uniqueId(): string
    {
        return $this->tanggal;
    }

    public function handle(): void
    {
        // TODO(modul Rekening): cari deposito dengan tanggal jatuh tempo = $this->tanggal,
        // lalu perpanjang otomatis atau pindahkan pokok + bunga ke tabungan.
    }
}
