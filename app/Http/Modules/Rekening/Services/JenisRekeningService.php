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
            ->orderByRaw('prefix is null desc')
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
            // Rekening utama (tanpa prefix) selalu di urutan pertama sebagai pilihan default.
            ->orderByRaw('prefix is null desc')
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
     * Kode tidak bisa diubah. Prefix boleh diubah; nomor rekening yang sudah ada
     * tetap, hanya rekening baru yang memakai prefix baru.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(JenisRekening $jenis, array $data): JenisRekening
    {
        unset($data['kode']);

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
