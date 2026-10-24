<?php

namespace App\Http\Modules\Rekening\Services;

use App\Http\Modules\Nasabah\Models\Nasabah;
use App\Http\Modules\Pengaturan\Services\PengaturanService;
use App\Http\Modules\Rekening\Models\JenisRekening;
use App\Http\Modules\Rekening\Models\Rekening;
use App\Http\Modules\Transaksi\Services\TransaksiService;
use App\Models\User;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RekeningService
{
    public function __construct(
        protected TransaksiService $transaksi,
        protected PengaturanService $pengaturan,
    ) {}

    /**
     * @param  array{jenis_rekening_id?: string|null, status?: string|null}  $filters
     */
    public function paginate(string $search = '', array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Rekening::query()
            ->with(['nasabah:id,nomor,nama,nis,kelas', 'jenisRekening:id,kode,nama'])
            ->when($search !== '', fn (Builder $query) => $this->cari($query, $search))
            ->when($filters['jenis_rekening_id'] ?? null, fn ($query, $jenis) => $query->where('jenis_rekening_id', $jenis))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderBy('nomor')
            ->paginate(min(max($perPage, 5), 100))
            ->withQueryString();
    }

    /**
     * Pencarian singkat untuk AutoComplete (rekening aktif).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function cariAktif(string $search, int $limit = 10): Collection
    {
        return Rekening::query()
            ->with(['nasabah:id,nomor,nama,kelas', 'jenisRekening:id,nama'])
            ->where('status', Rekening::AKTIF)
            ->when($search !== '', fn (Builder $query) => $this->cari($query, $search))
            ->orderBy('nomor')
            ->limit($limit)
            ->get()
            ->map(fn (Rekening $rekening) => $this->ringkas($rekening));
    }

    /**
     * @return array<string, mixed>
     */
    public function ringkas(Rekening $rekening): array
    {
        return [
            'id' => $rekening->id,
            'nomor' => $rekening->nomor,
            'jenis' => $rekening->jenisRekening?->nama,
            'saldo' => $rekening->saldo,
            'status' => $rekening->status,
            'nasabah' => $rekening->nasabah?->only('id', 'nomor', 'nama', 'kelas'),
        ];
    }

    /**
     * Membuka rekening baru untuk nasabah, sekaligus mencatat setoran awal.
     *
     * @throws ValidationException
     */
    public function buka(Nasabah $nasabah, JenisRekening $jenis, int|string $setoranAwal, ?User $user = null): Rekening
    {
        if (! $nasabah->is_active) {
            throw ValidationException::withMessages(['nasabah_id' => 'Nasabah tidak aktif.']);
        }

        if (! $jenis->is_active) {
            throw ValidationException::withMessages(['jenis_rekening_id' => "Jenis rekening {$jenis->nama} tidak aktif."]);
        }

        $minimal = $this->pengaturan->uang('setoran_awal_minimal');

        if (Money::compare($setoranAwal, $minimal) < 0 || ! Money::isPositive($setoranAwal)) {
            throw ValidationException::withMessages([
                'setoran_awal' => 'Setoran awal minimal '.Money::format(Money::isPositive($minimal) ? $minimal : '1').'.',
            ]);
        }

        $dasar = $this->nomorDasar($nasabah);

        // Nomor yang sama bisa direbut nasabah lain di saat bersamaan (mis. prefix 2 + NISN
        // 0012345678 = NISN 2001234567 + suffix 8); unique index menolak, lalu dicoba lagi.
        for ($percobaan = 1; ; $percobaan++) {
            try {
                return DB::transaction(function () use ($nasabah, $jenis, $dasar, $setoranAwal, $user) {
                    $rekening = Rekening::create([
                        'nomor' => $this->nomorBerikutnya($jenis, $nasabah, $dasar),
                        'nasabah_id' => $nasabah->id,
                        'jenis_rekening_id' => $jenis->id,
                        'saldo_awal' => '0.00',
                        'status' => Rekening::AKTIF,
                        'tanggal_buka' => $this->pengaturan->tanggalTransaksi(),
                    ]);

                    $this->transaksi->setor($rekening, $setoranAwal, $user, 'Setoran awal');

                    return $rekening->refresh();
                }, 3);
            } catch (UniqueConstraintViolationException $e) {
                if ($percobaan >= 3) {
                    throw $e;
                }
            }
        }
    }

    /**
     * @return array{biaya: string, diserahkan: string}
     */
    public function tutup(Rekening $rekening, ?User $user = null): array
    {
        return $this->transaksi->tutup($rekening, $user);
    }

    /**
     * Angka identitas nasabah sebagai dasar nomor rekening: NISN untuk siswa
     * (NIS bila NISN belum diisi), NIP untuk guru/karyawan. Hanya digitnya yang dipakai.
     *
     * @throws ValidationException
     */
    public function nomorDasar(Nasabah $nasabah): string
    {
        $siswa = $nasabah->jenis === 'siswa';
        $dasar = preg_replace('/\D/', '', (string) ($siswa ? ($nasabah->nisn ?: $nasabah->nis) : $nasabah->nis));

        if ($dasar === '') {
            throw ValidationException::withMessages([
                'nasabah_id' => $siswa
                    ? "Lengkapi NISN atau NIS {$nasabah->nama} terlebih dahulu; nomor rekening dibuat dari NISN/NIS."
                    : "Lengkapi NIP / No. pegawai {$nasabah->nama} terlebih dahulu; nomor rekening dibuat dari NIP.",
            ]);
        }

        return $dasar;
    }

    /**
     * Nomor = prefix angka jenis + nomor dasar, mis. 0012345678 (rekening utama,
     * prefix kosong) atau 20012345678 (prefix 2). Bila sudah dipakai, diberi suffix
     * urut tanpa pemisah: 00123456781, 00123456782, dst. Baris nasabah dikunci agar
     * dua rekening nasabah yang sama tidak dibuka bersamaan dengan nomor sama.
     */
    protected function nomorBerikutnya(JenisRekening $jenis, Nasabah $nasabah, string $dasar): string
    {
        Nasabah::query()->whereKey($nasabah->id)->lockForUpdate()->value('id');

        $nomor = ($jenis->prefix ?? '').$dasar;

        $terpakai = Rekening::query()->where('nomor', 'like', $nomor.'%')->pluck('nomor')->flip();

        if (! $terpakai->has($nomor)) {
            return $nomor;
        }

        $suffix = 1;

        while ($terpakai->has($nomor.$suffix)) {
            $suffix++;
        }

        return $nomor.$suffix;
    }

    protected function cari(Builder $query, string $search): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('nomor', 'like', "%{$search}%")
            ->orWhereHas('nasabah', fn (Builder $n) => $n
                ->where('nama', 'like', "%{$search}%")
                ->orWhere('nis', 'like', "%{$search}%")
                ->orWhere('nisn', 'like', "%{$search}%")
                ->orWhere('nomor', 'like', "%{$search}%")));
    }
}
