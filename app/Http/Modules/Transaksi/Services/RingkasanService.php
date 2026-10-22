<?php

namespace App\Http\Modules\Transaksi\Services;

use App\Http\Modules\Rekening\Models\Rekening;
use App\Http\Modules\Transaksi\Models\Transaksi;
use App\Support\Money;
use Illuminate\Support\Carbon;

/**
 * Ringkasan transaksi per tanggal transaksi (untuk dashboard & laporan harian).
 */
class RingkasanService
{
    /**
     * Transaksi yang memindahkan uang fisik di meja teller. Biaya administrasi
     * tidak termasuk: saldo nasabah berkurang tetapi tidak ada uang yang keluar.
     */
    public const JENIS_KAS = [Transaksi::SETOR, Transaksi::TARIK, Transaksi::PENUTUPAN];

    /**
     * @return array{setoran: string, jumlah_setoran: int, penarikan: string, jumlah_penarikan: int, mutasi_kas: string, total_saldo: string, jumlah_rekening: int}
     */
    public function harian(Carbon $tanggal): array
    {
        $hariIni = Transaksi::query()->whereDate('tanggal', $tanggal->toDateString());

        $setoran = (clone $hariIni)->where('jenis', Transaksi::SETOR);
        $penarikan = (clone $hariIni)->whereIn('jenis', [Transaksi::TARIK, Transaksi::PENUTUPAN]);

        $mutasiKas = (clone $hariIni)->whereIn('jenis', self::JENIS_KAS)->sum('mutasi');

        // Koreksi atas transaksi kas ikut mengubah kas (mis. setoran yang dibatalkan = uang dikembalikan).
        $pembalikKas = (clone $hariIni)
            ->where('jenis', Transaksi::PEMBALIK)
            ->whereHas('pembalikDari', fn ($query) => $query->whereIn('jenis', self::JENIS_KAS))
            ->sum('mutasi');

        $rekeningAktif = Rekening::query()->where('status', Rekening::AKTIF);

        return [
            'setoran' => Money::of((string) $setoran->sum('nominal')),
            'jumlah_setoran' => $setoran->count(),
            'penarikan' => Money::of((string) $penarikan->sum('nominal')),
            'jumlah_penarikan' => $penarikan->count(),
            'mutasi_kas' => Money::add((string) $mutasiKas, (string) $pembalikKas),
            'total_saldo' => Money::of((string) (clone $rekeningAktif)->sum('saldo')),
            'jumlah_rekening' => $rekeningAktif->count(),
        ];
    }
}
