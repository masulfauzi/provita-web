<?php

namespace App\Http\Modules\Akuntansi\Services;

use App\Http\Modules\Akuntansi\Models\Akun;
use App\Http\Modules\Akuntansi\Models\Jurnal;
use App\Http\Modules\Pengaturan\Services\PengaturanService;
use App\Http\Modules\Rekening\Models\Rekening;
use App\Http\Modules\Transaksi\Models\Transaksi;
use App\Models\User;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Satu-satunya pintu untuk mencatat jurnal. Setiap jurnal wajib seimbang
 * (total debit = total kredit) dan tidak bisa diubah; koreksi lewat balik().
 */
class JurnalService
{
    /**
     * Akun debit & kredit per jenis transaksi tabungan (berdasarkan peran akun).
     */
    public const POLA_TRANSAKSI = [
        Transaksi::SETOR => [Akun::KAS, Akun::SIMPANAN],
        Transaksi::TARIK => [Akun::SIMPANAN, Akun::KAS],
        Transaksi::PENUTUPAN => [Akun::SIMPANAN, Akun::KAS],
        Transaksi::BIAYA => [Akun::SIMPANAN, Akun::PENDAPATAN_ADMINISTRASI],
    ];

    public function __construct(protected PengaturanService $pengaturan) {}

    /**
     * @param  array{dari?: string|null, sampai?: string|null, sumber?: string|null}  $filters
     */
    public function paginate(string $search = '', array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return Jurnal::query()
            ->with(['detail.akun:id,kode,nama', 'user:id,name', 'pembalik:id,pembalik_dari_id,nomor'])
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('nomor', 'like', "%{$search}%")
                ->orWhere('keterangan', 'like', "%{$search}%")))
            ->when($filters['dari'] ?? null, fn ($query, $dari) => $query->whereDate('tanggal', '>=', $dari))
            ->when($filters['sampai'] ?? null, fn ($query, $sampai) => $query->whereDate('tanggal', '<=', $sampai))
            ->when($filters['sumber'] ?? null, fn ($query, $sumber) => $query->where('sumber', $sumber))
            ->orderByDesc('tanggal')
            ->orderByDesc('nomor')
            ->paginate(min(max($perPage, 10), 100))
            ->withQueryString();
    }

    /**
     * Mencatat jurnal manual (jurnal umum).
     *
     * @param  list<array{akun_id: string, debit?: int|string|null, kredit?: int|string|null, keterangan?: string|null}>  $baris
     *
     * @throws ValidationException
     */
    public function catat(Carbon|string $tanggal, string $keterangan, array $baris, ?User $user = null): Jurnal
    {
        return DB::transaction(fn () => $this->simpan([
            'tanggal' => Carbon::parse($tanggal)->toDateString(),
            'keterangan' => $keterangan,
            'sumber' => Jurnal::MANUAL,
            'user_id' => $user?->id,
        ], $baris), 3);
    }

    /**
     * Jurnal dari modul lain (mis. penerimaan/pengeluaran kas). Pemanggil menjalankannya
     * di dalam DB transaction miliknya agar catatan modul dan jurnal tersimpan bersama.
     *
     * @param  list<array{akun_id: string, debit?: int|string|null, kredit?: int|string|null, keterangan?: string|null}>  $baris
     *
     * @throws ValidationException
     */
    public function catatOtomatis(string $sumber, Carbon|string $tanggal, string $keterangan, array $baris, ?User $user = null): Jurnal
    {
        return $this->simpan([
            'tanggal' => Carbon::parse($tanggal)->toDateString(),
            'keterangan' => mb_strimwidth($keterangan, 0, 255),
            'sumber' => $sumber,
            'user_id' => $user?->id,
        ], $baris);
    }

    /**
     * Jurnal pembalik: debit & kredit ditukar, tanggal = tanggal transaksi aktif. Dari halaman
     * jurnal hanya jurnal manual yang bisa dikoreksi; jurnal kas dibalik lewat pembatalan kas
     * ($dariModul), jurnal transaksi lewat koreksi di buku tabungan.
     *
     * @throws ValidationException
     */
    public function balik(Jurnal $jurnal, ?User $user = null, ?string $alasan = null, bool $dariModul = false): Jurnal
    {
        if ($jurnal->sumber === Jurnal::KAS && ! $dariModul) {
            throw ValidationException::withMessages([
                'jurnal' => 'Jurnal dari penerimaan/pengeluaran kas dikoreksi dengan membatalkan bukti kasnya.',
            ]);
        }

        if (! in_array($jurnal->sumber, [Jurnal::MANUAL, Jurnal::KAS], true)) {
            throw ValidationException::withMessages([
                'jurnal' => 'Jurnal dari transaksi tabungan dikoreksi lewat koreksi transaksi di buku tabungan.',
            ]);
        }

        if ($jurnal->pembalik_dari_id) {
            throw ValidationException::withMessages(['jurnal' => 'Jurnal pembalik tidak dapat dibalik lagi.']);
        }

        return DB::transaction(function () use ($jurnal, $user, $alasan) {
            // Dikunci agar dua klik koreksi tidak membuat dua pembalik.
            Jurnal::query()->whereKey($jurnal->id)->lockForUpdate()->first();

            if (Jurnal::query()->where('pembalik_dari_id', $jurnal->id)->exists()) {
                throw ValidationException::withMessages(['jurnal' => "Jurnal {$jurnal->nomor} sudah pernah dikoreksi."]);
            }

            return $this->simpan([
                'tanggal' => $this->pengaturan->tanggalTransaksi()->toDateString(),
                'keterangan' => mb_strimwidth($alasan ? "Koreksi {$jurnal->nomor}: {$alasan}" : "Koreksi {$jurnal->nomor}", 0, 255),
                'sumber' => $jurnal->sumber,
                'pembalik_dari_id' => $jurnal->id,
                'user_id' => $user?->id,
            ], $jurnal->detail()->get()->map(fn ($detail) => [
                'akun_id' => $detail->akun_id,
                'debit' => $detail->kredit,
                'kredit' => $detail->debit,
                'keterangan' => $detail->keterangan,
            ])->all());
        }, 3);
    }

    /**
     * Jurnal otomatis untuk satu transaksi tabungan. Dipanggil oleh TransaksiService di dalam
     * DB transaction yang sama, sehingga transaksi dan jurnalnya selalu tersimpan bersama.
     */
    public function dariTransaksi(Transaksi $transaksi): Jurnal
    {
        $jenis = $transaksi->jenis;
        $tukar = false;

        // Koreksi memakai pola transaksi asal dengan debit & kredit ditukar.
        if ($jenis === Transaksi::PEMBALIK) {
            $jenis = Transaksi::query()->whereKey($transaksi->pembalik_dari_id)->value('jenis');
            $tukar = true;
        }

        [$debit, $kredit] = self::POLA_TRANSAKSI[$jenis]
            ?? throw new \LogicException("Jenis transaksi [{$jenis}] belum memiliki pola jurnal.");

        if ($tukar) {
            [$debit, $kredit] = [$kredit, $debit];
        }

        $rekening = Rekening::query()->with('nasabah:id,nama')->find($transaksi->rekening_id, ['id', 'nomor', 'nasabah_id']);
        $keterangan = Transaksi::JENIS[$transaksi->jenis].' '.$rekening?->nomor.($rekening?->nasabah ? ' · '.$rekening->nasabah->nama : '');

        return $this->simpan([
            'tanggal' => $transaksi->tanggal->toDateString(),
            'keterangan' => mb_strimwidth($keterangan, 0, 255),
            'sumber' => Jurnal::TRANSAKSI,
            'transaksi_id' => $transaksi->id,
            'user_id' => $transaksi->user_id,
        ], [
            ['akun_id' => Akun::peran($debit)->id, 'debit' => $transaksi->nominal],
            ['akun_id' => Akun::peran($kredit)->id, 'kredit' => $transaksi->nominal],
        ]);
    }

    /**
     * Mengunci mutex jurnal (baris akun kas). Dipanggil di dalam DB transaction sebelum
     * memeriksa saldo kas, sehingga pemeriksaan dan pencatatan tidak bisa diselip proses lain.
     */
    public function kunci(): void
    {
        Akun::query()->where('peran', Akun::KAS)->lockForUpdate()->value('id');
    }

    /**
     * Saldo akun kas dari seluruh jurnal.
     */
    public function saldoKas(): string
    {
        $saldo = DB::table('jurnal_detail')
            ->where('akun_id', Akun::peran(Akun::KAS)->id)
            ->first([DB::raw('SUM(debit) as debit'), DB::raw('SUM(kredit) as kredit')]);

        return Money::sub((string) ($saldo->debit ?? '0'), (string) ($saldo->kredit ?? '0'));
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $baris
     *
     * @throws ValidationException
     */
    protected function simpan(array $data, array $baris): Jurnal
    {
        $baris = $this->validasi($baris);

        $jurnal = Jurnal::create([...$data, 'nomor' => $this->nomorBerikutnya($data['tanggal'])]);

        foreach ($baris as $urut => $item) {
            $jurnal->detail()->create([...$item, 'urut' => $urut + 1]);
        }

        return $jurnal;
    }

    /**
     * @param  list<array<string, mixed>>  $baris
     * @return list<array{akun_id: string, debit: string, kredit: string, keterangan: string|null}>
     *
     * @throws ValidationException
     */
    protected function validasi(array $baris): array
    {
        $baris = array_values(array_map(fn (array $item) => [
            'akun_id' => $item['akun_id'] ?? null,
            'debit' => Money::of($item['debit'] ?? null),
            'kredit' => Money::of($item['kredit'] ?? null),
            'keterangan' => filled($item['keterangan'] ?? null) ? $item['keterangan'] : null,
        ], $baris));

        if (count($baris) < 2) {
            throw ValidationException::withMessages(['baris' => 'Jurnal minimal terdiri dari dua baris.']);
        }

        foreach ($baris as $i => $item) {
            $adaDebit = Money::isPositive($item['debit']);
            $adaKredit = Money::isPositive($item['kredit']);

            if ($adaDebit === $adaKredit || Money::isNegative($item['debit']) || Money::isNegative($item['kredit'])) {
                throw ValidationException::withMessages([
                    "baris.{$i}.debit" => 'Isi salah satu: debit atau kredit (lebih dari nol).',
                ]);
            }
        }

        $akunAktif = Akun::query()->aktif()->whereIn('id', array_column($baris, 'akun_id'))->pluck('id')->flip();

        foreach ($baris as $i => $item) {
            if (! $akunAktif->has($item['akun_id'])) {
                throw ValidationException::withMessages(["baris.{$i}.akun_id" => 'Akun tidak ditemukan atau tidak aktif.']);
            }
        }

        $debit = Money::add(...array_column($baris, 'debit'));
        $kredit = Money::add(...array_column($baris, 'kredit'));

        if (Money::compare($debit, $kredit) !== 0) {
            throw ValidationException::withMessages([
                'baris' => 'Jurnal tidak seimbang: debit '.Money::format($debit).', kredit '.Money::format($kredit).'.',
            ]);
        }

        return $baris;
    }

    /**
     * Nomor urut per tahun, mis. JU-2026-000001. Baris akun kas dipakai sebagai
     * mutex supaya dua jurnal yang dibuat bersamaan tidak mendapat nomor sama.
     */
    protected function nomorBerikutnya(string $tanggal): string
    {
        $this->kunci();

        $prefix = 'JU-'.substr($tanggal, 0, 4).'-';
        $terakhir = Jurnal::query()->where('nomor', 'like', $prefix.'%')->orderByDesc('nomor')->value('nomor');
        $urut = $terakhir ? (int) substr($terakhir, strlen($prefix)) + 1 : 1;

        return $prefix.str_pad((string) $urut, 6, '0', STR_PAD_LEFT);
    }
}
