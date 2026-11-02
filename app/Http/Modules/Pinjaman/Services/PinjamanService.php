<?php

namespace App\Http\Modules\Pinjaman\Services;

use App\Http\Modules\Akuntansi\Models\Akun;
use App\Http\Modules\Akuntansi\Services\JurnalService;
use App\Http\Modules\Kas\Services\SesiKasService;
use App\Http\Modules\Nasabah\Models\Nasabah;
use App\Http\Modules\Pengaturan\Services\PengaturanService;
use App\Http\Modules\Pinjaman\Models\Pinjaman;
use App\Http\Modules\Pinjaman\Models\PinjamanAngsuran;
use App\Http\Modules\Transaksi\Models\Transaksi;
use App\Models\User;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pinjaman tunai guru/karyawan dengan jasa flat per bulan. Pencairan dan angsuran adalah
 * uang fisik: dicatat di tabel transaksi (tanpa rekening) sehingga ikut sesi kas, laporan
 * kas harian, dan saldo kas. Jurnal:
 *  - pencairan: D Piutang pinjaman / K Kas
 *  - angsuran:  D Kas / K Piutang pinjaman (pokok) / K Pendapatan jasa pinjaman (jasa)
 *  - pembatalan: jurnal asal dengan debit & kredit ditukar.
 */
class PinjamanService
{
    public const PLAFON_DEFAULT = '10000000';

    public const JASA_DEFAULT = '1';

    public const TENOR_DEFAULT = 24;

    public function __construct(
        protected JurnalService $jurnal,
        protected PengaturanService $pengaturan,
        protected SesiKasService $sesi,
    ) {}

    /**
     * @param  array{status?: string|null}  $filters
     */
    public function paginate(string $search = '', array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $belum = fn (Builder $query) => $query->whereNull('dibayar_tanggal');

        return Pinjaman::query()
            ->with(['nasabah:id,nomor,nama,jenis', 'pembuat:id,name'])
            ->withSum(['angsuran as sisa_pokok' => $belum], 'pokok')
            ->withMin(['angsuran as jatuh_tempo_berikutnya' => $belum], 'jatuh_tempo')
            ->withCount(['angsuran as sisa_angsuran' => $belum])
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $q) => $q
                ->where('nomor', 'like', "%{$search}%")
                ->orWhereHas('nasabah', fn (Builder $n) => $n
                    ->where('nama', 'like', "%{$search}%")
                    ->orWhere('nomor', 'like', "%{$search}%"))))
            ->when($filters['status'] ?? null, fn (Builder $query, $status) => $query->where('status', $status))
            ->orderByDesc('tanggal')
            ->orderByDesc('nomor')
            ->paginate(min(max($perPage, 5), 100))
            ->withQueryString();
    }

    /**
     * Guru/karyawan aktif untuk pilihan peminjam, beserta pinjaman berjalannya (bila ada).
     *
     * @return list<array<string, mixed>>
     */
    public function cariPeminjam(string $search, int $limit = 10): array
    {
        return Nasabah::query()
            ->where('is_active', true)
            ->whereIn('jenis', Pinjaman::JENIS_NASABAH)
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $q) => $q
                ->where('nama', 'like', "%{$search}%")
                ->orWhere('nomor', 'like', "%{$search}%")
                ->orWhere('nis', 'like', "%{$search}%")
                ->orWhere('nik', 'like', "%{$search}%")))
            ->orderBy('nama')
            ->limit($limit)
            ->get(['id', 'nomor', 'nis', 'nama', 'jenis'])
            ->map(fn (Nasabah $nasabah) => [
                ...$nasabah->only('id', 'nomor', 'nis', 'nama'),
                'jenis' => Nasabah::JENIS[$nasabah->jenis] ?? $nasabah->jenis,
                'pinjaman_berjalan' => Pinjaman::query()
                    ->where('nasabah_id', $nasabah->id)
                    ->where('status', Pinjaman::BERJALAN)
                    ->value('nomor'),
            ])
            ->all();
    }

    /**
     * Plafon satu pinjaman; '0.00' berarti tanpa batas.
     */
    public function plafon(): string
    {
        return Money::of($this->pengaturan->get('plafon_pinjaman') ?? self::PLAFON_DEFAULT);
    }

    /**
     * Persen jasa flat per bulan dari pokok.
     */
    public function jasaPersen(): string
    {
        $nilai = $this->pengaturan->get('jasa_pinjaman_persen');

        return is_numeric($nilai) ? (string) $nilai : self::JASA_DEFAULT;
    }

    public function tenorMaksimal(): int
    {
        $nilai = $this->pengaturan->get('tenor_maksimal_pinjaman');

        return is_numeric($nilai) && (int) $nilai > 0 ? (int) $nilai : self::TENOR_DEFAULT;
    }

    /**
     * Jadwal angsuran flat. Pokok per bulan dibulatkan ke bawah ke rupiah dan sisanya masuk ke
     * angsuran terakhir; jasa per bulan = pokok × persen / 100 dibulatkan ke rupiah (half up).
     * Jatuh tempo ke-n = tanggal akad + n bulan (tanpa melewati akhir bulan).
     *
     * @return list<array{ke: int, jatuh_tempo: string, pokok: string, jasa: string, total: string}>
     */
    public function jadwal(int|string $pokok, int $tenor, int|string $persen, Carbon|string $tanggal): array
    {
        $pokok = Money::of($pokok);
        $tanggal = Carbon::parse($tanggal)->startOfDay();
        $perBulan = bcdiv($pokok, (string) $tenor, 0);
        $terakhir = Money::sub($pokok, bcmul($perBulan, (string) ($tenor - 1), Money::SCALE));
        $jasa = bcadd(bcdiv(bcmul($pokok, (string) $persen, 6), '100', 6), '0.5', 0);

        $jadwal = [];

        for ($ke = 1; $ke <= $tenor; $ke++) {
            $pokokKe = $ke === $tenor ? $terakhir : Money::of($perBulan);

            $jadwal[] = [
                'ke' => $ke,
                'jatuh_tempo' => $tanggal->copy()->addMonthsNoOverflow($ke)->toDateString(),
                'pokok' => $pokokKe,
                'jasa' => Money::of($jasa),
                'total' => Money::add($pokokKe, $jasa),
            ];
        }

        return $jadwal;
    }

    /**
     * Akad sekaligus pencairan tunai: membuat pinjaman, jadwal angsuran, transaksi
     * pencairan (uang keluar dari kas), dan jurnalnya.
     *
     * @throws ValidationException
     */
    public function buat(Nasabah $nasabah, int|string $pokok, int $tenor, ?string $keterangan = null, ?User $user = null): Pinjaman
    {
        $pokok = Money::of($pokok);
        $this->validasiAkad($nasabah, $pokok, $tenor);

        return DB::transaction(function () use ($nasabah, $pokok, $tenor, $keterangan, $user) {
            // Mutex jurnal: cek saldo kas, cek pinjaman berjalan, dan nomor tidak bisa diselip.
            $this->jurnal->kunci();
            $this->pastikanSesiTerbuka($user, 'pokok');

            if ($berjalan = Pinjaman::query()->where('nasabah_id', $nasabah->id)->where('status', Pinjaman::BERJALAN)->value('nomor')) {
                throw ValidationException::withMessages([
                    'nasabah_id' => "{$nasabah->nama} masih punya pinjaman berjalan ({$berjalan}). Lunasi dulu sebelum meminjam lagi.",
                ]);
            }

            $saldo = $this->jurnal->saldoKas();
            $this->pastikanSaldoCukup($saldo, $pokok, 'pokok');

            $tanggal = $this->pengaturan->tanggalTransaksi()->toDateString();
            $persen = $this->jasaPersen();
            $jadwal = $this->jadwal($pokok, $tenor, $persen, $tanggal);
            $nomor = $this->nomorBerikutnya('PJ-', $tanggal);

            $transaksi = Transaksi::create([
                'urut' => 0,
                'nomor' => $nomor,
                'tanggal' => $tanggal,
                'jenis' => Transaksi::PINJAMAN_CAIR,
                'nominal' => $pokok,
                'mutasi' => Money::negate($pokok),
                'saldo_sesudah' => Money::sub($saldo, $pokok),
                'keterangan' => mb_strimwidth("Pinjaman {$tenor} bulan".(filled($keterangan) ? " · {$keterangan}" : ''), 0, 255),
                'akun_id' => Akun::peran(Akun::PIUTANG_PINJAMAN)->id,
                'nasabah_id' => $nasabah->id,
                'user_id' => $user?->id,
            ]);

            $this->jurnal->dariTransaksi($transaksi);

            $pinjaman = Pinjaman::create([
                'nomor' => $nomor,
                'nasabah_id' => $nasabah->id,
                'tanggal' => $tanggal,
                'pokok' => $pokok,
                'tenor' => $tenor,
                'jasa_persen' => $persen,
                'total_jasa' => Money::add('0', ...array_column($jadwal, 'jasa')),
                'status' => Pinjaman::BERJALAN,
                'keterangan' => filled($keterangan) ? $keterangan : null,
                'transaksi_id' => $transaksi->id,
                'dibuat_oleh' => $user?->id,
            ]);

            $pinjaman->angsuran()->createMany($jadwal);

            return $pinjaman;
        }, 3);
    }

    /**
     * Membayar $jumlah angsuran berikutnya secara berurutan dalam satu transaksi tunai.
     *
     * @throws ValidationException
     */
    public function bayar(Pinjaman $pinjaman, int $jumlah, ?User $user = null): Transaksi
    {
        if ($jumlah < 1) {
            throw ValidationException::withMessages(['jumlah' => 'Pilih minimal satu angsuran.']);
        }

        return DB::transaction(function () use ($pinjaman, $jumlah, $user) {
            $this->jurnal->kunci();
            $this->pastikanSesiTerbuka($user, 'jumlah');
            $pinjaman = $this->kunci($pinjaman);

            if (! $pinjaman->isBerjalan()) {
                throw ValidationException::withMessages(['jumlah' => "Pinjaman {$pinjaman->nomor} sudah ".strtolower(Pinjaman::STATUS[$pinjaman->status]).'.']);
            }

            $belum = $pinjaman->angsuran()->whereNull('dibayar_tanggal')->orderBy('ke')->get();

            if ($jumlah > $belum->count()) {
                throw ValidationException::withMessages(['jumlah' => "Sisa angsuran hanya {$belum->count()} kali."]);
            }

            $dibayar = $belum->take($jumlah);
            $pokok = Money::add('0', ...$dibayar->pluck('pokok')->all());
            $jasa = Money::add('0', ...$dibayar->pluck('jasa')->all());
            $total = Money::add($pokok, $jasa);
            $tanggal = $this->pengaturan->tanggalTransaksi()->toDateString();
            $saldo = $this->jurnal->saldoKas();
            $ke = $dibayar->first()->ke === $dibayar->last()->ke ? $dibayar->first()->ke : "{$dibayar->first()->ke}–{$dibayar->last()->ke}";

            $transaksi = Transaksi::create([
                'urut' => 0,
                'nomor' => $this->nomorBerikutnya('AP-', $tanggal),
                'tanggal' => $tanggal,
                'jenis' => Transaksi::ANGSURAN,
                'nominal' => $total,
                'mutasi' => $total,
                'saldo_sesudah' => Money::add($saldo, $total),
                'keterangan' => "Angsuran ke-{$ke} pinjaman {$pinjaman->nomor}",
                'akun_id' => Akun::peran(Akun::PIUTANG_PINJAMAN)->id,
                'nasabah_id' => $pinjaman->nasabah_id,
                'user_id' => $user?->id,
            ]);

            $baris = [
                ['akun_id' => Akun::peran(Akun::KAS)->id, 'debit' => $total],
                ['akun_id' => Akun::peran(Akun::PIUTANG_PINJAMAN)->id, 'kredit' => $pokok, 'keterangan' => 'Pokok'],
            ];

            if (Money::isPositive($jasa)) {
                $baris[] = ['akun_id' => Akun::peran(Akun::PENDAPATAN_JASA_PINJAMAN)->id, 'kredit' => $jasa, 'keterangan' => 'Jasa'];
            }

            $this->jurnal->dariTransaksi($transaksi, $baris);

            PinjamanAngsuran::query()->whereKey($dibayar->modelKeys())->update([
                'dibayar_tanggal' => $tanggal,
                'transaksi_id' => $transaksi->id,
            ]);

            if ($jumlah === $belum->count()) {
                $pinjaman->update(['status' => Pinjaman::LUNAS, 'lunas_at' => now()]);
            }

            return $transaksi;
        }, 3);
    }

    /**
     * Membatalkan pembayaran angsuran terakhir (salah input): uang dikembalikan dari kas,
     * jurnal dibalik, dan baris jadwalnya kembali belum dibayar.
     *
     * @throws ValidationException
     */
    public function batalPembayaran(Pinjaman $pinjaman, string $alasan, ?User $user = null): Transaksi
    {
        return DB::transaction(function () use ($pinjaman, $alasan, $user) {
            $this->jurnal->kunci();
            $this->pastikanSesiTerbuka($user, 'alasan');
            $pinjaman = $this->kunci($pinjaman);

            $transaksiId = $pinjaman->angsuran()->whereNotNull('transaksi_id')->orderByDesc('ke')->value('transaksi_id');

            if ($transaksiId === null) {
                throw ValidationException::withMessages(['alasan' => 'Belum ada angsuran yang dibayar.']);
            }

            // Membuka kembali pinjaman lunas tidak boleh membuat dua pinjaman berjalan.
            if (! $pinjaman->isBerjalan() && Pinjaman::query()->where('nasabah_id', $pinjaman->nasabah_id)->where('status', Pinjaman::BERJALAN)->exists()) {
                throw ValidationException::withMessages(['alasan' => 'Nasabah ini sudah punya pinjaman berjalan yang lebih baru.']);
            }

            if ($pinjaman->status === Pinjaman::DIBATALKAN) {
                throw ValidationException::withMessages(['alasan' => "Pinjaman {$pinjaman->nomor} sudah dibatalkan."]);
            }

            $asal = Transaksi::query()->findOrFail($transaksiId);
            $pembalik = $this->balik($asal, $alasan, $user);

            PinjamanAngsuran::query()->where('transaksi_id', $asal->id)->update(['dibayar_tanggal' => null, 'transaksi_id' => null]);
            $pinjaman->update(['status' => Pinjaman::BERJALAN, 'lunas_at' => null]);

            return $pembalik;
        }, 3);
    }

    /**
     * Membatalkan pinjaman yang belum ada angsuran dibayar: uang pinjaman kembali ke kas.
     *
     * @throws ValidationException
     */
    public function batal(Pinjaman $pinjaman, string $alasan, ?User $user = null): Transaksi
    {
        return DB::transaction(function () use ($pinjaman, $alasan, $user) {
            $this->jurnal->kunci();
            $this->pastikanSesiTerbuka($user, 'alasan');
            $pinjaman = $this->kunci($pinjaman);

            if (! $pinjaman->isBerjalan()) {
                throw ValidationException::withMessages(['alasan' => "Pinjaman {$pinjaman->nomor} sudah ".strtolower(Pinjaman::STATUS[$pinjaman->status]).'.']);
            }

            if ($pinjaman->angsuran()->whereNotNull('dibayar_tanggal')->exists()) {
                throw ValidationException::withMessages(['alasan' => 'Sudah ada angsuran yang dibayar. Batalkan dulu pembayaran angsurannya.']);
            }

            $pembalik = $this->balik($pinjaman->transaksi()->firstOrFail(), $alasan, $user);
            $pinjaman->update(['status' => Pinjaman::DIBATALKAN]);

            return $pembalik;
        }, 3);
    }

    /**
     * Transaksi pembalik beserta jurnal baliknya. Dipanggil saat mutex jurnal sudah dikunci.
     *
     * @throws ValidationException
     */
    protected function balik(Transaksi $asal, string $alasan, ?User $user): Transaksi
    {
        if (Transaksi::query()->where('pembalik_dari_id', $asal->id)->exists()) {
            throw ValidationException::withMessages(['alasan' => "{$asal->nomor} sudah dibatalkan."]);
        }

        $mutasi = Money::negate($asal->mutasi);
        $saldo = $this->jurnal->saldoKas();

        // Membatalkan angsuran = mengembalikan uangnya, jadi saldo kas harus cukup.
        if (Money::isNegative($mutasi)) {
            $this->pastikanSaldoCukup($saldo, $asal->nominal, 'alasan');
        }

        $pembalik = Transaksi::create([
            'urut' => 0,
            'tanggal' => $this->pengaturan->tanggalTransaksi(),
            'jenis' => Transaksi::PEMBALIK,
            'nominal' => $asal->nominal,
            'mutasi' => $mutasi,
            'saldo_sesudah' => Money::add($saldo, $mutasi),
            'keterangan' => $alasan,
            'akun_id' => $asal->akun_id,
            'nasabah_id' => $asal->nasabah_id,
            'pembalik_dari_id' => $asal->id,
            'user_id' => $user?->id,
        ]);

        $this->jurnal->dariTransaksi($pembalik, $this->jurnal->barisTerbalik($asal));

        return $pembalik;
    }

    /**
     * @throws ValidationException
     */
    protected function validasiAkad(Nasabah $nasabah, string $pokok, int $tenor): void
    {
        if (! $nasabah->is_active || ! in_array($nasabah->jenis, Pinjaman::JENIS_NASABAH, true)) {
            throw ValidationException::withMessages(['nasabah_id' => 'Pinjaman hanya untuk nasabah guru/karyawan yang aktif.']);
        }

        if (! Money::isPositive($pokok) || Money::compare($pokok, bcadd($pokok, '0', 0)) !== 0) {
            throw ValidationException::withMessages(['pokok' => 'Pokok pinjaman harus rupiah bulat lebih dari Rp 0.']);
        }

        $plafon = $this->plafon();

        if (Money::isPositive($plafon) && Money::compare($pokok, $plafon) > 0) {
            throw ValidationException::withMessages(['pokok' => 'Pokok pinjaman melebihi plafon '.Money::format($plafon).'.']);
        }

        if ($tenor < 1 || $tenor > $this->tenorMaksimal()) {
            throw ValidationException::withMessages(['tenor' => "Jangka waktu 1 sampai {$this->tenorMaksimal()} bulan."]);
        }
    }

    protected function kunci(Pinjaman $pinjaman): Pinjaman
    {
        return Pinjaman::query()->whereKey($pinjaman->getKey())->lockForUpdate()->firstOrFail();
    }

    /**
     * @throws ValidationException
     */
    protected function pastikanSesiTerbuka(?User $user, string $field): void
    {
        if ($alasan = $this->sesi->alasanTolak($user)) {
            throw ValidationException::withMessages([$field => $alasan]);
        }
    }

    /**
     * @throws ValidationException
     */
    protected function pastikanSaldoCukup(string $saldo, string $nominal, string $field): void
    {
        if (Money::compare($saldo, $nominal) < 0) {
            throw ValidationException::withMessages([
                $field => 'Saldo kas tidak cukup: tersedia '.Money::format($saldo).', dibutuhkan '.Money::format($nominal).'.',
            ]);
        }
    }

    /**
     * PJ-2026-000001 (pencairan) / AP-2026-000001 (angsuran); dipanggil saat mutex jurnal dikunci.
     */
    protected function nomorBerikutnya(string $prefix, string $tanggal): string
    {
        $prefix .= substr($tanggal, 0, 4).'-';
        $terakhir = Transaksi::query()->where('nomor', 'like', $prefix.'%')->orderByDesc('nomor')->value('nomor');
        $urut = $terakhir ? (int) substr($terakhir, strlen($prefix)) + 1 : 1;

        return $prefix.str_pad((string) $urut, 6, '0', STR_PAD_LEFT);
    }
}
