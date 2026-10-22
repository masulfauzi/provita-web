<?php

namespace App\Http\Modules\Pengaturan\Services;

use App\Http\Modules\Pengaturan\Models\Pengaturan;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Akses pengaturan untuk modul lain. Modul lain cukup memanggil
 * PengaturanService::get()/tanggalTransaksi(), bukan query tabel langsung.
 */
class PengaturanService
{
    protected const CACHE_KEY = 'pengaturan.semua';

    /** @var array<string, string|null>|null */
    protected ?array $values = null;

    public function get(string $kunci, mixed $default = null): mixed
    {
        return $this->values()[$kunci] ?? $default;
    }

    public function uang(string $kunci): string
    {
        return Money::of($this->get($kunci, '0'));
    }

    public function tanggalTransaksi(): Carbon
    {
        $nilai = $this->get('tanggal_transaksi');

        return $nilai ? Carbon::parse($nilai)->startOfDay() : today();
    }

    public function set(string $kunci, mixed $nilai): void
    {
        $this->setMany([$kunci => $nilai]);
    }

    /**
     * @param  array<string, mixed>  $values  kunci => nilai
     */
    public function setMany(array $values): void
    {
        DB::transaction(function () use ($values) {
            $records = Pengaturan::whereIn('kunci', array_keys($values))->get()->keyBy('kunci');

            foreach ($values as $kunci => $nilai) {
                if ($pengaturan = $records->get($kunci)) {
                    $pengaturan->update(['nilai' => $this->normalize($pengaturan->tipe, $nilai)]);
                }
            }
        });

        $this->flush();
    }

    /**
     * Pengaturan dikelompokkan per grup, dengan tanda apakah user boleh mengubahnya.
     *
     * @return Collection<string, array{grup: string, editable: bool, items: Collection<int, Pengaturan>}>
     */
    public function groupedFor(User $user): Collection
    {
        return Pengaturan::query()->orderBy('urutan')->get()
            ->groupBy('grup')
            ->map(fn (Collection $items, string $grup) => [
                'grup' => $grup,
                'editable' => $this->canEditGroup($user, $grup),
                'items' => $items->values(),
            ]);
    }

    public function canEditGroup(User $user, string $grup): bool
    {
        $permission = Pengaturan::GRUP[$grup] ?? null;

        return $permission !== null && $user->can($permission);
    }

    public function flush(): void
    {
        $this->values = null;
        Cache::forget(self::CACHE_KEY);
    }

    protected function normalize(string $tipe, mixed $nilai): ?string
    {
        if ($nilai === null || $nilai === '') {
            return $tipe === 'uang' ? '0.00' : null;
        }

        return match ($tipe) {
            'uang' => Money::of($nilai),
            'tanggal' => Carbon::parse($nilai)->toDateString(),
            default => (string) $nilai,
        };
    }

    /**
     * @return array<string, string|null>
     */
    protected function values(): array
    {
        try {
            return $this->values ??= Cache::rememberForever(
                self::CACHE_KEY,
                fn () => Pengaturan::query()->pluck('nilai', 'kunci')->all(),
            );
        } catch (QueryException) {
            // Tabel belum dimigrasi (instalasi baru).
            return [];
        }
    }
}
