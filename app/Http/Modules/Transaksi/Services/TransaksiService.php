<?php

namespace App\Http\Modules\Transaksi\Services;

use App\Http\Modules\Pengaturan\Services\PengaturanService;
use App\Http\Modules\Rekening\Models\Rekening;
use App\Http\Modules\Transaksi\Exceptions\TransaksiDitolakException;
use App\Http\Modules\Transaksi\Models\Transaksi;
use App\Models\User;
use App\Support\Finance\FinancialTransaction;
use App\Support\Money;

/**
 * Satu-satunya pintu untuk mengubah saldo rekening. Semua operasi
 * mengunci baris rekening, sehingga aman dipakai banyak teller bersamaan.
 */
class TransaksiService
{
    public function __construct(protected PengaturanService $pengaturan) {}

    public function setor(Rekening|string $rekening, int|string $nominal, ?User $user = null, ?string $keterangan = null): Transaksi
    {
        $nominal = $this->nominal($nominal);

        return $this->catat($rekening, Transaksi::SETOR, $nominal, $nominal, $user, $keterangan);
    }

    public function tarik(Rekening|string $rekening, int|string $nominal, ?User $user = null, ?string $keterangan = null): Transaksi
    {
        $nominal = $this->nominal($nominal);

        return $this->catat($rekening, Transaksi::TARIK, $nominal, Money::negate($nominal), $user, $keterangan);
    }

    /**
     * Koreksi: membuat transaksi baru dengan mutasi berlawanan.
     */
    public function balik(Transaksi $transaksi, ?User $user = null, ?string $alasan = null): Transaksi
    {
        if ($transaksi->jenis === Transaksi::PEMBALIK) {
            throw TransaksiDitolakException::pembalikTidakBisaDibalik();
        }

        return $this->catat(
            $transaksi->rekening_id,
            Transaksi::PEMBALIK,
            $transaksi->nominal,
            Money::negate($transaksi->mutasi),
            $user,
            $alasan ?? "Pembalik transaksi {$transaksi->id}",
            $transaksi,
        );
    }

    /**
     * Saldo sebenarnya: saldo awal + jumlah semua mutasi.
     */
    public function hitungSaldo(Rekening $rekening): string
    {
        $mutasi = Transaksi::query()->where('rekening_id', $rekening->id)->sum('mutasi');

        return Money::add($rekening->saldo_awal, Money::of((string) $mutasi));
    }

    protected function catat(
        Rekening|string $rekening,
        string $jenis,
        string $nominal,
        string $mutasi,
        ?User $user,
        ?string $keterangan,
        ?Transaksi $pembalikDari = null,
    ): Transaksi {
        $id = $rekening instanceof Rekening ? $rekening->getKey() : $rekening;

        return FinancialTransaction::lock(Rekening::query()->whereKey($id), function (Rekening $rekening) use (
            $jenis, $nominal, $mutasi, $user, $keterangan, $pembalikDari
        ) {
            if ($rekening->status !== 'aktif') {
                throw TransaksiDitolakException::rekeningTidakAktif();
            }

            if ($pembalikDari && Transaksi::where('pembalik_dari_id', $pembalikDari->id)->exists()) {
                throw TransaksiDitolakException::sudahDibalik();
            }

            $saldoBaru = Money::add($rekening->saldo, $mutasi);

            // Saldo tidak boleh negatif, termasuk akibat transaksi pembalik.
            if (Money::isNegative($saldoBaru)) {
                throw TransaksiDitolakException::saldoTidakCukup(Money::format($rekening->saldo), Money::format($nominal));
            }

            $transaksi = Transaksi::create([
                'rekening_id' => $rekening->id,
                'tanggal' => $this->pengaturan->tanggalTransaksi(),
                'jenis' => $jenis,
                'nominal' => $nominal,
                'mutasi' => $mutasi,
                'saldo_sesudah' => $saldoBaru,
                'keterangan' => $keterangan,
                'pembalik_dari_id' => $pembalikDari?->id,
                'user_id' => $user?->id,
            ]);

            $rekening->forceFill(['saldo' => $saldoBaru])->save();

            return $transaksi;
        });
    }

    protected function nominal(int|string $nominal): string
    {
        $nominal = Money::of($nominal);

        if (! Money::isPositive($nominal)) {
            throw TransaksiDitolakException::nominalTidakValid();
        }

        return $nominal;
    }
}
