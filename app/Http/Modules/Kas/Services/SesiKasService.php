<?php

namespace App\Http\Modules\Kas\Services;

use App\Http\Modules\Kas\Models\SesiKas;
use App\Http\Modules\Nasabah\Models\Nasabah;
use App\Http\Modules\Pengaturan\Services\PengaturanService;
use App\Http\Modules\Transaksi\Models\Transaksi;
use App\Models\User;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sesi kas harian: satu sesi per tanggal transaksi, dipakai bersama semua petugas.
 * Siapa pun (teller maupun bendahara) hanya boleh menggerakkan uang fisik selama
 * sesi tanggal transaksi terbuka.
 */
class SesiKasService
{
    public function __construct(protected PengaturanService $pengaturan) {}

    /**
     * Sesi pada tanggal transaksi aktif (terbuka maupun sudah ditutup).
     */
    public function hariIni(): ?SesiKas
    {
        return SesiKas::query()->whereDate('tanggal', $this->pengaturan->tanggalTransaksi())->first();
    }

    /**
     * Sesi yang belum ditutup (paling banyak satu, dijaga oleh buka()).
     */
    public function terbuka(): ?SesiKas
    {
        return SesiKas::query()->whereNull('ditutup_at')->orderBy('tanggal')->first();
    }

    /**
     * Status sesi tanggal transaksi aktif: belum | terbuka | ditutup.
     */
    public function status(): string
    {
        $sesi = $this->hariIni();

        return match (true) {
            $sesi === null => 'belum',
            $sesi->isDitutup() => 'ditutup',
            default => 'terbuka',
        };
    }

    /**
     * Saran modal awal: uang fisik saat sesi terakhir ditutup.
     */
    public function saranModal(): string
    {
        return Money::of(SesiKas::query()->whereNotNull('ditutup_at')->orderByDesc('tanggal')->value('uang_fisik') ?? '0');
    }

    public function riwayat(int $perPage = 10): LengthAwarePaginator
    {
        return SesiKas::query()
            ->with(['pembuka:id,name', 'penutup:id,name', 'petugas:id,nama,kelas'])
            ->orderByDesc('tanggal')
            ->paginate(min(max($perPage, 5), 100))
            ->withQueryString();
    }

    /**
     * Membuka sesi dengan modal awal dan siswa piket yang hadir. Jam input modal
     * dicatat sebagai jam hadir (presensi) setiap siswa piket.
     *
     * @param  list<string>  $petugas  id nasabah siswa piket
     *
     * @throws ValidationException
     */
    public function buka(int|string $modal, array $petugas, ?User $user = null): SesiKas
    {
        $modal = Money::of($modal);
        $tanggal = $this->pengaturan->tanggalTransaksi();
        $petugas = array_values(array_unique($petugas));

        if (Money::isNegative($modal)) {
            throw ValidationException::withMessages(['modal_awal' => 'Modal awal tidak boleh negatif.']);
        }

        if ($petugas === []) {
            throw ValidationException::withMessages(['petugas' => 'Pilih minimal satu teller piket.']);
        }

        $siswa = Nasabah::query()->whereKey($petugas)->where('jenis', 'siswa')->where('is_active', true)->orderBy('nama')->get(['id', 'nama']);

        if ($siswa->count() !== count($petugas)) {
            throw ValidationException::withMessages(['petugas' => 'Teller piket harus siswa yang terdaftar sebagai nasabah aktif.']);
        }

        try {
            return DB::transaction(function () use ($modal, $tanggal, $user, $siswa) {
                if ($ada = SesiKas::query()->whereDate('tanggal', $tanggal)->lockForUpdate()->first()) {
                    throw ValidationException::withMessages([
                        'modal_awal' => "Sesi kas tanggal {$this->tanggal($tanggal)} sudah ".($ada->isDitutup() ? 'ditutup.' : 'dibuka.'),
                    ]);
                }

                if ($lama = $this->terbuka()) {
                    throw ValidationException::withMessages([
                        'modal_awal' => "Sesi kas tanggal {$this->tanggal($lama->tanggal)} belum ditutup. Tutup dulu sesi tersebut.",
                    ]);
                }

                $sesi = SesiKas::create([
                    'tanggal' => $tanggal->toDateString(),
                    'modal_awal' => $modal,
                    'dibuka_oleh' => $user?->id,
                    'dibuka_at' => $hadir = now(),
                ]);

                $sesi->petugas()->attach($siswa->modelKeys(), ['hadir_at' => $hadir]);

                activity('sesi_kas')->performedOn($sesi)->causedBy($user)->event('petugas')
                    ->withProperties(['petugas' => $siswa->pluck('nama')->all(), 'hadir_at' => $hadir->toDateTimeString()])
                    ->log('Teller piket hadir');

                return $sesi->load('petugas');
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['modal_awal' => "Sesi kas tanggal {$this->tanggal($tanggal)} sudah dibuka."]);
        }
    }

    /**
     * Menutup sesi: saldo sistem dibekukan, uang fisik dicocokkan. Selisih wajib dijelaskan.
     *
     * @throws ValidationException
     */
    public function tutup(SesiKas $sesi, int|string $uangFisik, ?string $keterangan = null, ?User $user = null): SesiKas
    {
        $uangFisik = Money::of($uangFisik);

        if (Money::isNegative($uangFisik)) {
            throw ValidationException::withMessages(['uang_fisik' => 'Uang fisik tidak boleh negatif.']);
        }

        return DB::transaction(function () use ($sesi, $uangFisik, $keterangan, $user) {
            // Transaksi teller membaca sesi dengan shared lock, jadi tidak ada yang menyelip saat tutup.
            $sesi = SesiKas::query()->whereKey($sesi->getKey())->lockForUpdate()->firstOrFail();

            if ($sesi->isDitutup()) {
                throw ValidationException::withMessages(['sesi' => "Sesi kas tanggal {$this->tanggal($sesi->tanggal)} sudah ditutup."]);
            }

            $saldo = $this->rekap($sesi->tanggal)['saldo_akhir'];
            $selisih = Money::sub($uangFisik, $saldo);

            if (Money::compare($selisih, 0) !== 0 && blank($keterangan)) {
                throw ValidationException::withMessages([
                    'keterangan' => 'Ada selisih '.Money::format($selisih).'. Jelaskan penyebabnya di keterangan.',
                ]);
            }

            $sesi->update([
                'saldo_sistem' => $saldo,
                'uang_fisik' => $uangFisik,
                'selisih' => $selisih,
                'keterangan' => $keterangan,
                'ditutup_oleh' => $user?->id,
                'ditutup_at' => now(),
            ]);

            return $sesi;
        }, 3);
    }

    /**
     * Alasan transaksi uang fisik ditolak, atau null bila boleh. Tanpa user (proses sistem,
     * mis. command console) tidak diperiksa. Dipanggil di dalam DB transaction pemanggil;
     * baris sesi dibaca dengan shared lock agar tutup() menunggu.
     */
    public function alasanTolak(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }

        $tanggal = $this->pengaturan->tanggalTransaksi();
        $sesi = SesiKas::query()->whereDate('tanggal', $tanggal)->sharedLock()->first();

        return match (true) {
            $sesi === null => "Sesi kas tanggal {$this->tanggal($tanggal)} belum dibuka. Buka sesi kas terlebih dahulu.",
            $sesi->isDitutup() => "Sesi kas tanggal {$this->tanggal($tanggal)} sudah ditutup.",
            default => null,
        };
    }

    /**
     * Rekap uang fisik pada satu tanggal: modal awal + pemasukan - pengeluaran.
     * Biaya administrasi tidak dihitung karena tidak ada uang yang berpindah;
     * koreksi & pembatalan masuk ke pemasukan atau pengeluaran menurut arah mutasinya.
     *
     * @return array<string, mixed>
     */
    public function rekap(Carbon|string $tanggal): array
    {
        $tanggal = Carbon::parse($tanggal)->toDateString();
        $sesi = SesiKas::query()->with(['pembuka:id,name', 'penutup:id,name', 'petugas:id,nama,nis,kelas'])->whereDate('tanggal', $tanggal)->first();

        $transaksi = Transaksi::query()
            ->whereDate('tanggal', $tanggal)
            ->where('jenis', '!=', Transaksi::BIAYA)
            ->with([
                'rekening:id,nomor,nasabah_id',
                'rekening.nasabah:id,nama',
                'nasabah:id,nama',
                'akun:id,kode,nama',
                'user:id,name',
                'pembalikDari:id,jenis,nomor',
            ])
            ->orderBy('created_at')
            ->orderBy('urut')
            ->get()
            ->reject(fn (Transaksi $item) => $item->pembalikDari?->jenis === Transaksi::BIAYA)
            ->values();

        $kelompok = ['pemasukan' => [], 'pengeluaran' => []];

        foreach ($transaksi as $item) {
            $arah = Money::isNegative($item->mutasi) ? 'pengeluaran' : 'pemasukan';
            [$kunci, $kode, $nama] = $this->kelompok($item);

            $kelompok[$arah][$kunci] ??= ['id' => $kunci, 'kode' => $kode, 'nama' => $nama, 'jumlah' => 0, 'nilai' => '0.00'];
            $kelompok[$arah][$kunci]['jumlah']++;
            $kelompok[$arah][$kunci]['nilai'] = Money::add($kelompok[$arah][$kunci]['nilai'], $item->nominal);
        }

        $modal = $sesi ? Money::of($sesi->modal_awal) : '0.00';
        $totalMasuk = Money::add('0', ...array_column($kelompok['pemasukan'], 'nilai'));
        $totalKeluar = Money::add('0', ...array_column($kelompok['pengeluaran'], 'nilai'));

        return [
            'tanggal' => $tanggal,
            'sesi' => $sesi ? [
                ...$sesi->only('id', 'modal_awal', 'dibuka_at', 'ditutup_at', 'saldo_sistem', 'uang_fisik', 'selisih', 'keterangan'),
                'pembuka' => $sesi->pembuka?->name,
                'penutup' => $sesi->penutup?->name,
                'petugas' => $sesi->petugas->map(fn (Nasabah $siswa) => [
                    ...$siswa->only('id', 'nama', 'nis', 'kelas'),
                    'hadir_at' => Carbon::parse($siswa->pivot->hadir_at)->toJSON(),
                ])->all(),
            ] : null,
            'modal_awal' => $modal,
            'pemasukan' => array_values($kelompok['pemasukan']),
            'pengeluaran' => array_values($kelompok['pengeluaran']),
            'total_pemasukan' => $totalMasuk,
            'total_pengeluaran' => $totalKeluar,
            'saldo_akhir' => Money::sub(Money::add($modal, $totalMasuk), $totalKeluar),
            'setelah_tutup' => $sesi?->isDitutup() && $transaksi->contains(fn (Transaksi $item) => $item->created_at->greaterThan($sesi->ditutup_at)),
            'rincian' => $transaksi->map(fn (Transaksi $item) => [
                'id' => $item->id,
                'waktu' => $item->created_at->format('H:i'),
                'jenis' => $this->labelJenis($item),
                'referensi' => $item->rekening?->nomor ?? $item->nomor ?? $item->pembalikDari?->nomor,
                'nasabah' => $item->rekening?->nasabah?->nama ?? $item->nasabah?->nama,
                'keterangan' => $item->keterangan,
                'masuk' => Money::isNegative($item->mutasi) ? null : Money::of($item->nominal),
                'keluar' => Money::isNegative($item->mutasi) ? Money::of($item->nominal) : null,
                'petugas' => $item->user?->name,
            ])->all(),
        ];
    }

    /**
     * @return array{0: string, 1: string, 2: string} kunci, kode, nama kelompok
     */
    protected function kelompok(Transaksi $item): array
    {
        return match ($item->jenis) {
            Transaksi::SETOR => ['setor', '', 'Setoran tabungan'],
            Transaksi::TARIK => ['tarik', '', 'Penarikan tabungan'],
            Transaksi::PENUTUPAN => ['penutupan', '', 'Penutupan rekening'],
            Transaksi::KAS_MASUK, Transaksi::KAS_KELUAR => ["akun-{$item->akun_id}", (string) $item->akun?->kode, (string) $item->akun?->nama],
            default => ['koreksi', '', 'Koreksi & pembatalan'],
        };
    }

    protected function labelJenis(Transaksi $item): string
    {
        if ($item->jenis !== Transaksi::PEMBALIK) {
            return Transaksi::JENIS[$item->jenis];
        }

        return $item->isKasLain() ? 'Pembatalan '.$item->pembalikDari?->nomor : 'Koreksi';
    }

    protected function tanggal(Carbon|string $tanggal): string
    {
        return Carbon::parse($tanggal)->translatedFormat('j F Y');
    }
}
