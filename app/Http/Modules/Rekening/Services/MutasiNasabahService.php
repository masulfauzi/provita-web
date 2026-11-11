<?php

namespace App\Http\Modules\Rekening\Services;

use App\Http\Modules\Rekening\Models\Rekening;
use App\Http\Modules\Transaksi\Models\Transaksi;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Mutasi rekening untuk Internet Banking: rentang tanggal (maks. 3 bulan), daftar transaksi
 * terbaru dulu, dan ringkasan periode yang dihitung di database.
 */
class MutasiNasabahService
{
    /**
     * Rentang periode dari query string. Default: 3 bulan terakhir s.d. hari ini.
     *
     * @param  array<string, mixed>  $input
     * @return array{0: Carbon, 1: Carbon}
     *
     * @throws ValidationException
     */
    public function rentang(array $input): array
    {
        $data = Validator::make($input, [
            'dari' => ['nullable', 'date_format:Y-m-d'],
            'sampai' => ['nullable', 'date_format:Y-m-d'],
        ], [
            'date_format' => 'Format tanggal tidak valid.',
        ])->validate();

        $bulan = (int) config('ib.mutasi_maks_bulan', 3);
        $sampai = isset($data['sampai']) ? Carbon::parse($data['sampai'])->startOfDay() : today();
        $dari = isset($data['dari']) ? Carbon::parse($data['dari'])->startOfDay() : $sampai->copy()->subMonthsNoOverflow($bulan);

        if ($dari->gt($sampai)) {
            throw ValidationException::withMessages(['dari' => 'Tanggal awal harus sebelum tanggal akhir.']);
        }

        if ($dari->lt($sampai->copy()->subMonthsNoOverflow($bulan))) {
            throw ValidationException::withMessages(['dari' => "Rentang tanggal maksimal {$bulan} bulan."]);
        }

        return [$dari, $sampai];
    }

    /**
     * Transaksi rekening dalam periode, terbaru dulu. Transaksi kas (tanpa rekening) tidak ikut.
     *
     * @return Builder<Transaksi>
     */
    public function query(Rekening $rekening, Carbon $dari, Carbon $sampai, ?string $jenis = null): Builder
    {
        return Transaksi::query()
            ->where('rekening_id', $rekening->id)
            ->whereBetween('tanggal', [$dari->toDateString(), $sampai->toDateString()])
            ->when($jenis === 'masuk', fn (Builder $q) => $q->where('mutasi', '>', 0))
            ->when($jenis === 'keluar', fn (Builder $q) => $q->where('mutasi', '<', 0))
            ->with('user:id,name')
            ->orderByDesc('tanggal')
            ->orderByDesc('urut');
    }

    /**
     * Ringkasan seluruh periode (tidak terpengaruh filter jenis maupun paginasi).
     *
     * @return array{saldo_awal: string, total_masuk: string, total_keluar: string, saldo_akhir: string, jumlah_masuk: int, jumlah_keluar: int}
     */
    public function ringkasan(Rekening $rekening, Carbon $dari, Carbon $sampai): array
    {
        $saldoAwal = Transaksi::query()
            ->where('rekening_id', $rekening->id)
            ->where('tanggal', '<', $dari->toDateString())
            ->orderByDesc('urut')
            ->value('saldo_sesudah') ?? $rekening->saldo_awal;

        $total = Transaksi::query()
            ->where('rekening_id', $rekening->id)
            ->whereBetween('tanggal', [$dari->toDateString(), $sampai->toDateString()])
            ->selectRaw('COALESCE(SUM(CASE WHEN mutasi > 0 THEN nominal ELSE 0 END), 0) as masuk')
            ->selectRaw('COALESCE(SUM(CASE WHEN mutasi < 0 THEN nominal ELSE 0 END), 0) as keluar')
            ->selectRaw('COALESCE(SUM(CASE WHEN mutasi > 0 THEN 1 ELSE 0 END), 0) as jumlah_masuk')
            ->selectRaw('COALESCE(SUM(CASE WHEN mutasi < 0 THEN 1 ELSE 0 END), 0) as jumlah_keluar')
            ->toBase()
            ->first();

        // SQLite bisa mengembalikan float untuk SUM desimal; MySQL mengembalikan string.
        $angka = fn ($nilai) => Money::of(is_float($nilai) ? sprintf('%.2f', $nilai) : (string) $nilai);
        $masuk = $angka($total->masuk);
        $keluar = $angka($total->keluar);

        return [
            'saldo_awal' => Money::of($saldoAwal),
            'total_masuk' => $masuk,
            'total_keluar' => $keluar,
            'saldo_akhir' => Money::sub(Money::add($saldoAwal, $masuk), $keluar),
            'jumlah_masuk' => (int) $total->jumlah_masuk,
            'jumlah_keluar' => (int) $total->jumlah_keluar,
        ];
    }
}
