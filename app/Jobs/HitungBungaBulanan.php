<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

/**
 * Menghitung & membukukan bunga tabungan untuk satu bulan.
 * Kerangka saja; logika diisi di issue modul Bunga.
 */
class HitungBungaBulanan implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public string $periode) {}

    public static function untukBulanLalu(): self
    {
        return new self(Carbon::now()->subMonthNoOverflow()->format('Y-m'));
    }

    public function uniqueId(): string
    {
        return $this->periode;
    }

    public function handle(): void
    {
        // TODO(modul Bunga): hitung bunga per rekening untuk $this->periode lewat
        // BungaService, lalu catat sebagai transaksi (idempoten per periode).
    }
}
