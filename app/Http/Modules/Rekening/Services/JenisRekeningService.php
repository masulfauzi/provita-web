<?php

namespace App\Http\Modules\Rekening\Services;

use App\Http\Modules\Rekening\Models\JenisRekening;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class JenisRekeningService
{
    /**
     * @return Collection<int, JenisRekening>
     */
    public function semua(): Collection
    {
        return JenisRekening::query()
            ->withCount('rekening')
            ->orderByDesc('is_active')
            ->orderBy('nama')
            ->get();
    }

    /**
     * Pilihan untuk Select. $hanyaAktif untuk form buka rekening; filter dan label
     * tetap memakai semua jenis agar rekening dari jenis nonaktif masih terbaca.
     *
     * @return list<array{value: string, label: string}>
     */
    public function options(bool $hanyaAktif = false): array
    {
        return JenisRekening::query()
            ->when($hanyaAktif, fn ($query) => $query->aktif())
            // Tabungan (jenis bawaan) selalu di urutan pertama sebagai pilihan default.
            ->orderByRaw('kode = ? desc', [JenisRekening::TABUNGAN])
            ->orderBy('nama')
            ->get(['id', 'nama'])
            ->map(fn (JenisRekening $jenis) => ['value' => $jenis->id, 'label' => $jenis->nama])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): JenisRekening
    {
        return JenisRekening::create($data);
    }

    /**
     * Kode tidak bisa diubah. Prefix hanya bisa diubah selama belum ada rekening,
     * karena nomor rekening lama sudah memakai prefix tersebut.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function update(JenisRekening $jenis, array $data): JenisRekening
    {
        unset($data['kode']);

        if (isset($data['prefix']) && $data['prefix'] !== $jenis->prefix && $jenis->rekening()->exists()) {
            throw ValidationException::withMessages([
                'prefix' => 'Prefix tidak bisa diubah karena sudah ada rekening dengan jenis ini.',
            ]);
        }

        $jenis->update($data);

        return $jenis;
    }

    public function setActive(JenisRekening $jenis, bool $active): void
    {
        $jenis->update(['is_active' => $active]);
    }

    /**
     * @throws ValidationException
     */
    public function delete(JenisRekening $jenis): void
    {
        if ($jenis->rekening()->exists()) {
            throw ValidationException::withMessages([
                'jenis_rekening' => 'Jenis rekening sudah dipakai rekening sehingga tidak bisa dihapus. Gunakan Nonaktifkan.',
            ]);
        }

        $jenis->delete();
    }
}
