<?php

namespace App\Http\Modules\Kas\Services;

use App\Http\Modules\Akuntansi\Models\Akun;
use App\Http\Modules\Akuntansi\Services\JurnalService;
use App\Http\Modules\Kas\Models\Kas;
use App\Http\Modules\Nasabah\Models\Nasabah;
use App\Http\Modules\Pengaturan\Services\PengaturanService;
use App\Models\User;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Penerimaan & pengeluaran kas. Setiap catatan langsung dijurnal di DB transaction
 * yang sama: penerimaan D Kas / K akun pendapatan, pengeluaran D akun beban / K Kas.
 */
class KasService
{
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
            ->with(['akun:id,kode,nama', 'nasabah:id,nomor,nama', 'user:id,name'])
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
        return Money::of((string) $this->query($jenis, $search, $filters)->whereNull('batal_at')->sum('nominal'));
    }

    /**
     * Akun lawan yang bisa dipilih: pendapatan untuk penerimaan, beban untuk pengeluaran.
     *
     * @return list<array{value: string, label: string, peran: string|null}>
     */
    public function akunOptions(string $jenis): array
    {
        return Akun::query()->aktif()
            ->where('kategori', Kas::JENIS[$jenis][2])
            ->orderBy('kode')
            ->get(['id', 'kode', 'nama', 'peran'])
            ->map(fn (Akun $akun) => ['value' => $akun->id, 'label' => "{$akun->kode} · {$akun->nama}", 'peran' => $akun->peran])
            ->all();
    }

    /**
     * @throws ValidationException
     */
    public function terima(Akun $akun, int|string $nominal, string $keterangan, ?Nasabah $nasabah = null, ?User $user = null): Kas
    {
        return $this->catat(Kas::MASUK, $akun, $nominal, $keterangan, $nasabah, $user);
    }

    /**
     * @throws ValidationException
     */
    public function keluarkan(Akun $akun, int|string $nominal, string $keterangan, ?User $user = null): Kas
    {
        return $this->catat(Kas::KELUAR, $akun, $nominal, $keterangan, null, $user);
    }

    /**
     * Membatalkan dengan jurnal pembalik. Catatan kas tetap tersimpan dengan tanda batal.
     *
     * @throws ValidationException
     */
    public function batal(Kas $kas, string $alasan, ?User $user = null): Kas
    {
        return DB::transaction(function () use ($kas, $alasan, $user) {
            $this->jurnal->kunci();
            $kas = Kas::query()->whereKey($kas->id)->lockForUpdate()->firstOrFail();

            if ($kas->dibatalkan()) {
                throw ValidationException::withMessages(['kas' => "{$kas->nomor} sudah dibatalkan."]);
            }

            // Membatalkan penerimaan = mengeluarkan uangnya lagi, jadi saldo kas harus cukup.
            if ($kas->jenis === Kas::MASUK) {
                $this->pastikanSaldoCukup($kas->nominal);
            }

            $pembalik = $this->jurnal->balik($kas->jurnal()->firstOrFail(), $user, "Pembatalan {$kas->nomor}: {$alasan}", dariModul: true);

            $kas->forceFill([
                'batal_at' => now(),
                'batal_alasan' => $alasan,
                'batal_user_id' => $user?->id,
                'jurnal_batal_id' => $pembalik->id,
            ])->save();

            return $kas;
        }, 3);
    }

    /**
     * @throws ValidationException
     */
    protected function catat(string $jenis, Akun $akun, int|string $nominal, string $keterangan, ?Nasabah $nasabah, ?User $user): Kas
    {
        $kategori = Kas::JENIS[$jenis][2];

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

            if ($jenis === Kas::KELUAR) {
                $this->pastikanSaldoCukup($nominal);
            }

            $tanggal = $this->pengaturan->tanggalTransaksi()->toDateString();
            $nomor = $this->nomorBerikutnya($jenis, $tanggal);
            $kas = Akun::peran(Akun::KAS)->id;

            $jurnal = $this->jurnal->catatOtomatis('kas', $tanggal, "{$nomor} · {$keterangan}", $jenis === Kas::MASUK
                ? [['akun_id' => $kas, 'debit' => $nominal], ['akun_id' => $akun->id, 'kredit' => $nominal]]
                : [['akun_id' => $akun->id, 'debit' => $nominal], ['akun_id' => $kas, 'kredit' => $nominal]], $user);

            return Kas::create([
                'nomor' => $nomor,
                'jenis' => $jenis,
                'tanggal' => $tanggal,
                'akun_id' => $akun->id,
                'nominal' => $nominal,
                'keterangan' => $keterangan,
                'nasabah_id' => $nasabah?->id,
                'jurnal_id' => $jurnal->id,
                'user_id' => $user?->id,
            ]);
        }, 3);
    }

    /**
     * @throws ValidationException
     */
    protected function pastikanSaldoCukup(string $nominal): void
    {
        $saldo = $this->jurnal->saldoKas();

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
        $prefix = Kas::JENIS[$jenis][1].substr($tanggal, 0, 4).'-';
        $terakhir = Kas::query()->where('nomor', 'like', $prefix.'%')->orderByDesc('nomor')->value('nomor');
        $urut = $terakhir ? (int) substr($terakhir, strlen($prefix)) + 1 : 1;

        return $prefix.str_pad((string) $urut, 6, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array{dari?: string|null, sampai?: string|null, akun_id?: string|null}  $filters
     * @return Builder<Kas>
     */
    protected function query(string $jenis, string $search, array $filters): Builder
    {
        return Kas::query()
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
