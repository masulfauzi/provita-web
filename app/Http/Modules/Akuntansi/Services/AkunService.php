<?php

namespace App\Http\Modules\Akuntansi\Services;

use App\Http\Modules\Akuntansi\Models\Akun;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AkunService
{
    /**
     * @return Collection<int, Akun>
     */
    public function semua(): Collection
    {
        $urutan = array_flip(array_keys(Akun::KATEGORI));

        // Diurutkan per kategori (aset → beban) agar tampil berkelompok di tabel.
        return Akun::query()->withCount('detail')->orderBy('kode')->get()
            ->sortBy(fn (Akun $akun) => $urutan[$akun->kategori] ?? 99, SORT_REGULAR)
            ->values();
    }

    /**
     * Pilihan akun aktif untuk form jurnal, dikelompokkan per kategori.
     *
     * @return list<array{label: string, items: list<array{value: string, label: string}>}>
     */
    public function options(): array
    {
        $akun = Akun::query()->aktif()->orderBy('kode')->get(['id', 'kode', 'nama', 'kategori'])->groupBy('kategori');

        return collect(Akun::KATEGORI)
            ->filter(fn (string $label, string $kategori) => $akun->has($kategori))
            ->map(fn (string $label, string $kategori) => [
                'label' => $label,
                'items' => $akun[$kategori]->map(fn (Akun $item) => [
                    'value' => $item->id,
                    'label' => "{$item->kode} · {$item->nama}",
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Akun
    {
        return Akun::create($data);
    }

    /**
     * Kategori terkunci untuk akun sistem atau yang sudah dipakai jurnal,
     * karena mengubahnya akan memindahkan saldo ke pos laporan lain.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function update(Akun $akun, array $data): Akun
    {
        if (isset($data['kategori']) && $data['kategori'] !== $akun->kategori && ($akun->peran || $akun->detail()->exists())) {
            throw ValidationException::withMessages([
                'kategori' => 'Kategori akun sistem atau akun yang sudah dipakai jurnal tidak bisa diubah.',
            ]);
        }

        $akun->update($data);

        return $akun;
    }

    /**
     * @throws ValidationException
     */
    public function setActive(Akun $akun, bool $active): void
    {
        if (! $active && $akun->peran) {
            throw ValidationException::withMessages(['akun' => "Akun {$akun->nama} dipakai jurnal otomatis sehingga tidak bisa dinonaktifkan."]);
        }

        $akun->update(['is_active' => $active]);
    }

    /**
     * @throws ValidationException
     */
    public function delete(Akun $akun): void
    {
        if ($akun->peran || $akun->detail()->exists()) {
            throw ValidationException::withMessages([
                'akun' => 'Akun sistem atau akun yang sudah dipakai jurnal tidak bisa dihapus. Gunakan Nonaktifkan.',
            ]);
        }

        $akun->delete();
    }
}
