<?php

namespace App\Http\Modules\Kas\Services;

use App\Http\Modules\Akuntansi\Models\Akun;
use App\Http\Modules\Akuntansi\Services\JurnalService;
use App\Http\Modules\Nasabah\Models\Nasabah;
use App\Http\Modules\Pengaturan\Services\PengaturanService;
use App\Http\Modules\Transaksi\Models\Transaksi;
use App\Models\User;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Penerimaan & pengeluaran kas. Dicatat di tabel transaksi tanpa rekening, memakai kas yang
 * sama dengan uang tabungan nasabah; saldo rekening nasabah tidak berubah. Jurnalnya
 * memakai akun lawan sendiri: penerimaan D Kas / K pendapatan, pengeluaran D beban / K Kas.
 */
class KasService
{
    /**
     * jenis => [judul menu, prefix nomor, kategori akun lawan].
     */
    public const JENIS = [
        Transaksi::KAS_MASUK => ['Penerimaan kas', 'KM-', 'pendapatan'],
        Transaksi::KAS_KELUAR => ['Pengeluaran kas', 'KK-', 'beban'],
    ];

    public function __construct(
        protected JurnalService $jurnal,
        protected PengaturanService $pengaturan,
    ) {}

    /**
     * @param  array{dari?: string|null, sampai?: string|null, akun_id?: string|null}  $filters
     */
    public function paginate(string $jenis, string $search = '', array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->query($jenis, $search, $filters)
            ->with(['akun:id,kode,nama', 'nasabah:id,nomor,nama', 'user:id,name', 'pembalik:id,pembalik_dari_id,created_at'])
            ->orderByDesc('tanggal')
            ->orderByDesc('nomor')
            ->paginate(min(max($perPage, 5), 100))
            ->withQueryString();
    }

    /**
     * Total nominal (yang tidak dibatalkan) sesuai filter.
     *
     * @param  array{dari?: string|null, sampai?: string|null, akun_id?: string|null}  $filters
     */
    public function total(string $jenis, string $search = '', array $filters = []): string
    {
        return Money::of((string) $this->query($jenis, $search, $filters)->doesntHave('pembalik')->sum('nominal'));
    }

    /**
     * Akun lawan yang bisa dipilih: pendapatan untuk penerimaan, beban untuk pengeluaran.
     *
     * @return list<array{value: string, label: string, peran: string|null}>
     */
    public function akunOptions(string $jenis): array
    {
        return Akun::query()->aktif()
            ->where('kategori', self::JENIS[$jenis][2])
            ->orderBy('kode')
            ->get(['id', 'kode', 'nama', 'peran'])
            ->map(fn (Akun $akun) => ['value' => $akun->id, 'label' => "{$akun->kode} · {$akun->nama}", 'peran' => $akun->peran])
            ->all();
    }

    /**
     * Saldo kas yang tersedia (termasuk uang tabungan nasabah).
     */
    public function saldoKas(): string
    {
        return $this->jurnal->saldoKas();
    }

    /**
     * @throws ValidationException
     */
    public function terima(Akun $akun, int|string $nominal, string $keterangan, ?Nasabah $nasabah = null, ?User $user = null): Transaksi
    {
        return $this->catat(Transaksi::KAS_MASUK, $akun, $nominal, $keterangan, $nasabah, $user);
    }

    /**
     * @throws ValidationException
     */
    public function keluarkan(Akun $akun, int|string $nominal, string $keterangan, ?User $user = null): Transaksi
    {
        return $this->catat(Transaksi::KAS_KELUAR, $akun, $nominal, $keterangan, null, $user);
    }

    /**
     * Membatalkan dengan transaksi pembalik (dan jurnal baliknya). Bukti asal tetap tersimpan.
     *
     * @throws ValidationException
     */
    public function batal(Transaksi $kas, string $alasan, ?User $user = null): Transaksi
    {
        return DB::transaction(function () use ($kas, $alasan, $user) {
            $this->jurnal->kunci();

            if (Transaksi::query()->where('pembalik_dari_id', $kas->id)->exists()) {
                throw ValidationException::withMessages(['kas' => "{$kas->nomor} sudah dibatalkan."]);
            }

            $mutasi = Money::negate($kas->mutasi);
            $saldo = $this->jurnal->saldoKas();

            // Membatalkan penerimaan = mengeluarkan uangnya lagi, jadi saldo kas harus cukup.
            if (Money::isNegative($mutasi)) {
                $this->pastikanSaldoCukup($saldo, $kas->nominal);
            }

            $pembalik = Transaksi::create([
                'urut' => 0,
                'tanggal' => $this->pengaturan->tanggalTransaksi(),
                'jenis' => Transaksi::PEMBALIK,
                'nominal' => $kas->nominal,
                'mutasi' => $mutasi,
                'saldo_sesudah' => Money::add($saldo, $mutasi),
                'keterangan' => $alasan,
                'akun_id' => $kas->akun_id,
                'pembalik_dari_id' => $kas->id,
                'user_id' => $user?->id,
            ]);

            $this->jurnal->dariTransaksi($pembalik);

            return $pembalik;
        }, 3);
    }

    /**
     * @throws ValidationException
     */
    protected function catat(string $jenis, Akun $akun, int|string $nominal, string $keterangan, ?Nasabah $nasabah, ?User $user): Transaksi
    {
        $kategori = self::JENIS[$jenis][2];

        if (! $akun->is_active || $akun->kategori !== $kategori) {
            throw ValidationException::withMessages(['akun_id' => 'Pilih kategori dari akun '.Akun::KATEGORI[$kategori].' yang aktif.']);
        }

        $nominal = Money::of($nominal);

        if (! Money::isPositive($nominal)) {
            throw ValidationException::withMessages(['nominal' => 'Nominal harus lebih dari nol.']);
        }

        return DB::transaction(function () use ($jenis, $akun, $nominal, $keterangan, $nasabah, $user) {
            // Mutex jurnal dikunci lebih dulu: cek saldo, nomor kas, dan nomor jurnal tidak bisa diselip.
            $this->jurnal->kunci();

            $saldo = $this->jurnal->saldoKas();
            $mutasi = $jenis === Transaksi::KAS_MASUK ? $nominal : Money::negate($nominal);

            if ($jenis === Transaksi::KAS_KELUAR) {
                $this->pastikanSaldoCukup($saldo, $nominal);
            }

            $tanggal = $this->pengaturan->tanggalTransaksi()->toDateString();

            $transaksi = Transaksi::create([
                'urut' => 0,
                'nomor' => $this->nomorBerikutnya($jenis, $tanggal),
                'tanggal' => $tanggal,
                'jenis' => $jenis,
                'nominal' => $nominal,
                'mutasi' => $mutasi,
                'saldo_sesudah' => Money::add($saldo, $mutasi),
                'keterangan' => $keterangan,
                'akun_id' => $akun->id,
                'nasabah_id' => $nasabah?->id,
                'user_id' => $user?->id,
            ]);

            $this->jurnal->dariTransaksi($transaksi);

            return $transaksi;
        }, 3);
    }

    /**
     * @throws ValidationException
     */
    protected function pastikanSaldoCukup(string $saldo, string $nominal): void
    {
        if (Money::compare($saldo, $nominal) < 0) {
            throw ValidationException::withMessages([
                'nominal' => 'Saldo kas tidak cukup: tersedia '.Money::format($saldo).', dibutuhkan '.Money::format($nominal).'.',
            ]);
        }
    }

    /**
     * KM-2026-000001 / KK-2026-000001; dipanggil saat mutex jurnal sudah dikunci.
     */
    protected function nomorBerikutnya(string $jenis, string $tanggal): string
    {
        $prefix = self::JENIS[$jenis][1].substr($tanggal, 0, 4).'-';
        $terakhir = Transaksi::query()->where('nomor', 'like', $prefix.'%')->orderByDesc('nomor')->value('nomor');
        $urut = $terakhir ? (int) substr($terakhir, strlen($prefix)) + 1 : 1;

        return $prefix.str_pad((string) $urut, 6, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array{dari?: string|null, sampai?: string|null, akun_id?: string|null}  $filters
     * @return Builder<Transaksi>
     */
    protected function query(string $jenis, string $search, array $filters): Builder
    {
        return Transaksi::query()
            ->kasLain()
            ->where('jenis', $jenis)
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $q) => $q
                ->where('nomor', 'like', "%{$search}%")
                ->orWhere('keterangan', 'like', "%{$search}%")
                ->orWhereHas('nasabah', fn (Builder $n) => $n->where('nama', 'like', "%{$search}%"))))
            ->when($filters['dari'] ?? null, fn (Builder $query, $dari) => $query->whereDate('tanggal', '>=', $dari))
            ->when($filters['sampai'] ?? null, fn (Builder $query, $sampai) => $query->whereDate('tanggal', '<=', $sampai))
            ->when($filters['akun_id'] ?? null, fn (Builder $query, $akun) => $query->where('akun_id', $akun));
    }
}
