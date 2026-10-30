<?php

namespace App\Http\Modules\Nasabah\Services;

use App\Http\Modules\Nasabah\Models\Nasabah;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NasabahService
{
    public const PREFIX_NOMOR = 'NSB-';

    /**
     * @param  array{jenis?: string|null, kelas?: string|null, status?: string|null}  $filters
     */
    public function paginate(string $search = '', array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Nasabah::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('nama', 'like', "%{$search}%")
                ->orWhere('nomor', 'like', "%{$search}%")
                ->orWhere('nis', 'like', "%{$search}%")
                ->orWhere('nisn', 'like', "%{$search}%")
                ->orWhere('nik', 'like', "%{$search}%")))
            ->when($filters['jenis'] ?? null, fn ($query, $jenis) => $query->where('jenis', $jenis))
            ->when($filters['kelas'] ?? null, fn ($query, $kelas) => $query->where('kelas', $kelas))
            ->when(($filters['status'] ?? null) === 'aktif', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'nonaktif', fn ($query) => $query->where('is_active', false))
            ->orderBy('nama')
            ->paginate(min(max($perPage, 5), 100))
            ->withQueryString();
    }

    /**
     * @return Collection<int, Nasabah>
     */
    public function cariAktif(string $search, int $limit = 10, ?string $jenis = null): Collection
    {
        return Nasabah::query()
            ->where('is_active', true)
            ->when($jenis, fn ($query) => $query->where('jenis', $jenis))
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('nama', 'like', "%{$search}%")
                ->orWhere('nomor', 'like', "%{$search}%")
                ->orWhere('nis', 'like', "%{$search}%")
                ->orWhere('nisn', 'like', "%{$search}%")
                ->orWhere('nik', 'like', "%{$search}%")))
            ->orderBy('nama')
            ->limit($limit)
            ->get(['id', 'nomor', 'nis', 'nisn', 'nama', 'kelas']);
    }

    /**
     * @return Collection<int, string>
     */
    public function daftarKelas(): Collection
    {
        return Nasabah::query()->whereNotNull('kelas')->distinct()->orderBy('kelas')->pluck('kelas');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Nasabah
    {
        return DB::transaction(function () use ($data) {
            $nasabah = new Nasabah($data);
            $nasabah->nomor = $this->nomorBerikutnya();
            $nasabah->save();

            return $nasabah;
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Nasabah $nasabah, array $data): Nasabah
    {
        $nasabah->update($data);

        return $nasabah;
    }

    public function setActive(Nasabah $nasabah, bool $active): void
    {
        $nasabah->update(['is_active' => $active]);
    }

    /**
     * Nasabah yang sudah memiliki rekening tidak boleh dihapus (riwayat
     * transaksinya harus tetap ada); gunakan nonaktifkan.
     *
     * @throws ValidationException
     */
    public function delete(Nasabah $nasabah): void
    {
        if ($nasabah->rekening()->exists()) {
            throw ValidationException::withMessages([
                'nasabah' => 'Nasabah sudah memiliki rekening sehingga tidak bisa dihapus. Gunakan Nonaktifkan.',
            ]);
        }

        $nasabah->delete();
    }

    /**
     * Nomor urut NSB-000001, NSB-000002, ... Baris nomor terakhir dikunci
     * agar dua teller yang mendaftar bersamaan tidak mendapat nomor sama.
     */
    protected function nomorBerikutnya(): string
    {
        $terakhir = Nasabah::query()
            ->where('nomor', 'like', self::PREFIX_NOMOR.'%')
            ->orderByDesc('nomor')
            ->lockForUpdate()
            ->value('nomor');

        $urut = $terakhir ? (int) substr($terakhir, strlen(self::PREFIX_NOMOR)) + 1 : 1;

        return self::PREFIX_NOMOR.str_pad((string) $urut, 6, '0', STR_PAD_LEFT);
    }
}
