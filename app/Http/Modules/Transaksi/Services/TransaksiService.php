<?php

namespace App\Http\Modules\Transaksi\Services;

use App\Http\Modules\Akuntansi\Services\JurnalService;
use App\Http\Modules\Pengaturan\Services\PengaturanService;
use App\Http\Modules\Rekening\Models\Rekening;
use App\Http\Modules\Transaksi\Exceptions\TransaksiDitolakException;
use App\Http\Modules\Transaksi\Models\Transaksi;
use App\Models\User;
use App\Support\Finance\FinancialTransaction;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

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
     * Menutup rekening: potong biaya administrasi penutupan (maksimal sebesar
     * saldo), lalu sisa saldo diserahkan ke nasabah. Semua dalam satu lock.
     *
     * @return array{biaya: string, diserahkan: string}
     */
    public function tutup(Rekening|string $rekening, ?User $user = null): array
    {
        $id = $rekening instanceof Rekening ? $rekening->getKey() : $rekening;

        return FinancialTransaction::lock(Rekening::query()->whereKey($id), function (Rekening $rekening) use ($user) {
            $this->pastikanAktif($rekening);

            $biaya = $this->biayaPenutupan($rekening->saldo);

            if (Money::isPositive($biaya)) {
                $this->mutasi($rekening, Transaksi::BIAYA, $biaya, Money::negate($biaya), $user, 'Biaya administrasi penutupan');
            }

            $diserahkan = Money::of($rekening->saldo);

            if (Money::isPositive($diserahkan)) {
                $this->mutasi($rekening, Transaksi::PENUTUPAN, $diserahkan, Money::negate($diserahkan), $user, 'Penutupan rekening');
            }

            $rekening->forceFill([
                'status' => Rekening::TUTUP,
                'tanggal_tutup' => $this->pengaturan->tanggalTransaksi(),
            ])->save();

            return ['biaya' => $biaya, 'diserahkan' => $diserahkan];
        });
    }

    /**
     * Biaya penutupan dari pengaturan adm_penutupan, tidak melebihi saldo.
     */
    public function biayaPenutupan(int|string $saldo): string
    {
        $biaya = $this->pengaturan->uang('adm_penutupan');

        return Money::compare($biaya, $saldo) > 0 ? Money::of($saldo) : $biaya;
    }

    /**
     * Mutasi rekening untuk buku tabungan, terbaru di atas.
     */
    public function bukuTabungan(Rekening $rekening, int $perPage = 25): LengthAwarePaginator
    {
        return $rekening->transaksi()
            ->with(['user:id,name', 'pembalik:id,pembalik_dari_id'])
            ->orderByDesc('urut')
            ->paginate(min(max($perPage, 10), 100))
            ->withQueryString();
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
            $this->pastikanAktif($rekening);

            if ($pembalikDari && Transaksi::where('pembalik_dari_id', $pembalikDari->id)->exists()) {
                throw TransaksiDitolakException::sudahDibalik();
            }

            return $this->mutasi($rekening, $jenis, $nominal, $mutasi, $user, $keterangan, $pembalikDari);
        });
    }

    /**
     * Mencatat satu mutasi pada rekening yang SUDAH dikunci oleh pemanggil.
     */
    protected function mutasi(
        Rekening $rekening,
        string $jenis,
        string $nominal,
        string $mutasi,
        ?User $user,
        ?string $keterangan,
        ?Transaksi $pembalikDari = null,
    ): Transaksi {
        $saldoBaru = Money::add($rekening->saldo, $mutasi);

        // Saldo tidak boleh negatif, termasuk akibat transaksi pembalik.
        if (Money::isNegative($saldoBaru)) {
            throw TransaksiDitolakException::saldoTidakCukup(Money::format($rekening->saldo), Money::format($nominal));
        }

        $transaksi = Transaksi::create([
            'rekening_id' => $rekening->id,
            'urut' => (int) Transaksi::where('rekening_id', $rekening->id)->max('urut') + 1,
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

        // Jurnal ikut dicatat di DB transaction yang sama: gagal satu, batal semua.
        if (class_exists(JurnalService::class)) {
            app(JurnalService::class)->dariTransaksi($transaksi);
        }

        return $transaksi;
    }

    protected function pastikanAktif(Rekening $rekening): void
    {
        if (! $rekening->isAktif()) {
            throw TransaksiDitolakException::rekeningTidakAktif();
        }
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
