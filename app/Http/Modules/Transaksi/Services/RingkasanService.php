<?php

namespace App\Http\Modules\Transaksi\Services;

use App\Http\Modules\Akuntansi\Models\Akun;
use App\Http\Modules\Akuntansi\Models\Jurnal;
use App\Http\Modules\Rekening\Models\Rekening;
use App\Http\Modules\Transaksi\Models\Transaksi;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
     * @return array{setoran: string, jumlah_setoran: int, penarikan: string, jumlah_penarikan: int, kas_lain: string, mutasi_kas: string, total_saldo: string, jumlah_rekening: int}
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
        $kasLain = $this->kasLain($tanggal);

        return [
            'setoran' => Money::of((string) $setoran->sum('nominal')),
            'jumlah_setoran' => $setoran->count(),
            'penarikan' => Money::of((string) $penarikan->sum('nominal')),
            'jumlah_penarikan' => $penarikan->count(),
            'kas_lain' => $kasLain,
            'mutasi_kas' => Money::add((string) $mutasiKas, (string) $pembalikKas, $kasLain),
            'total_saldo' => Money::of((string) (clone $rekeningAktif)->sum('saldo')),
            'jumlah_rekening' => $rekeningAktif->count(),
        ];
    }

    /**
     * Penerimaan − pengeluaran kas lain-lain (modul Kas) pada tanggal tersebut, dari jurnalnya,
     * sehingga pembatalan ikut dihitung pada hari pembatalannya.
     */
    protected function kasLain(Carbon $tanggal): string
    {
        if (! class_exists(Jurnal::class) || ! class_exists('App\\Http\\Modules\\Kas\\Models\\Kas')) {
            return '0.00';
        }

        $kas = DB::table('jurnal_detail as d')
            ->join('jurnal as j', 'j.id', '=', 'd.jurnal_id')
            ->where('j.sumber', Jurnal::KAS)
            ->whereDate('j.tanggal', $tanggal->toDateString())
            ->where('d.akun_id', Akun::peran(Akun::KAS)->id)
            ->first([DB::raw('SUM(d.debit) as debit'), DB::raw('SUM(d.kredit) as kredit')]);

        return Money::sub((string) ($kas->debit ?? '0'), (string) ($kas->kredit ?? '0'));
    }
}
