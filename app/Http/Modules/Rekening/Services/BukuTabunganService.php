<?php

namespace App\Http\Modules\Rekening\Services;

use App\Http\Modules\Pengaturan\Services\PengaturanService;
use App\Http\Modules\Rekening\Models\CetakBukuTabungan;
use App\Http\Modules\Rekening\Models\Rekening;
use App\Http\Modules\Transaksi\Models\Transaksi;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Cetak buku tabungan fisik. Status cetak disimpan di cetak_buku_tabungan karena
 * transaksi immutable; posisi berikutnya melanjutkan (halaman, baris) cetakan terakhir.
 */
class BukuTabunganService
{
    /** Sandi transaksi di kolom "Sandi" buku tabungan. */
    public const SANDI = [
        Transaksi::SETOR => '01',
        Transaksi::TARIK => '02',
        Transaksi::PEMBALIK => '03',
        Transaksi::BIAYA => '04',
        Transaksi::PENUTUPAN => '05',
    ];

    /** Lebar kolom No, Tanggal, Sandi, Debit, Kredit, Saldo, Paraf (mm). */
    public const LEBAR_KOLOM = [10, 20, 10, 30, 30, 32, 14];

    public function __construct(protected PengaturanService $pengaturan) {}

    /**
     * Mutasi yang akan dicetak, urut nomor baris. Mode: belum (default) | nomor | tanggal.
     *
     * @param  array{mode?: string|null, dari_no?: int|null, sampai_no?: int|null, dari?: string|null, sampai?: string|null}  $filter
     * @return list<array<string, mixed>>
     */
    public function baris(Rekening $rekening, array $filter = []): array
    {
        $mode = $filter['mode'] ?? 'belum';

        return Transaksi::query()
            ->where('rekening_id', $rekening->id)
            ->with('user:id,name')
            ->withCount('cetakBuku')
            ->when($mode === 'belum', fn (Builder $query) => $query->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('cetak_buku_tabungan')->whereColumn('cetak_buku_tabungan.transaksi_id', 'transaksi.id')))
            ->when($mode === 'nomor', fn (Builder $query) => $query
                ->when($filter['dari_no'] ?? null, fn (Builder $q, $no) => $q->where('urut', '>=', $no))
                ->when($filter['sampai_no'] ?? null, fn (Builder $q, $no) => $q->where('urut', '<=', $no)))
            ->when($mode === 'tanggal', fn (Builder $query) => $query
                ->when($filter['dari'] ?? null, fn (Builder $q, $tanggal) => $q->whereDate('tanggal', '>=', $tanggal))
                ->when($filter['sampai'] ?? null, fn (Builder $q, $tanggal) => $q->whereDate('tanggal', '<=', $tanggal)))
            ->orderBy('urut')
            ->get()
            ->map(fn (Transaksi $item) => [
                'id' => $item->id,
                'no' => $item->urut,
                'tanggal' => $item->tanggal->toDateString(),
                'sandi' => self::SANDI[$item->jenis] ?? '00',
                'debit' => Money::isNegative($item->mutasi) ? Money::of($item->nominal) : null,
                'kredit' => Money::isNegative($item->mutasi) ? null : Money::of($item->nominal),
                'saldo' => Money::of($item->saldo_sesudah),
                'paraf' => $this->inisial($item->user?->name),
                'dicetak' => $item->cetak_buku_count,
            ])
            ->all();
    }

    /**
     * Jumlah mutasi rekening yang belum pernah dicetak.
     */
    public function jumlahBelumDicetak(Rekening $rekening): int
    {
        return count($this->baris($rekening));
    }

    /**
     * Posisi terjauh yang sudah terisi di buku, atau null bila belum pernah dicetak.
     *
     * @return array{halaman: int, baris: int}|null
     */
    public function posisiTerakhir(Rekening $rekening): ?array
    {
        $terakhir = CetakBukuTabungan::query()->where('rekening_id', $rekening->id)
            ->orderByDesc('halaman')->orderByDesc('baris')->first();

        return $terakhir?->only('halaman', 'baris');
    }

    /**
     * Posisi cetak berikutnya: satu baris setelah posisi terjauh yang sudah terisi.
     *
     * @return array{halaman: int, baris: int}
     */
    public function posisiBerikutnya(Rekening $rekening): array
    {
        $terakhir = $this->posisiTerakhir($rekening);

        return $terakhir ? $this->geser($terakhir['halaman'], $terakhir['baris'], 1) : ['halaman' => 1, 'baris' => 1];
    }

    /**
     * Tata letak cetak dari pengaturan; nilai yang tidak masuk akal memakai default.
     *
     * @return array{baris_per_halaman: int, jarak_atas: int, jarak_kiri: int, tinggi_baris: int, lebar_kolom: list<int>}
     */
    public function tataLetak(): array
    {
        $angka = fn (string $kunci, int $default, int $min, int $max) => ($nilai = (int) $this->pengaturan->get($kunci)) >= $min && $nilai <= $max ? $nilai : $default;

        $lebar = array_map('intval', array_map('trim', explode(',', (string) $this->pengaturan->get('buku_lebar_kolom_mm'))));
        $lebarValid = count($lebar) === count(self::LEBAR_KOLOM) && min($lebar) > 0 && max($lebar) <= 200;

        return [
            'baris_per_halaman' => $angka('buku_baris_per_halaman', 20, 1, 100),
            'jarak_atas' => $angka('buku_jarak_atas_mm', 20, 0, 300),
            'jarak_kiri' => $angka('buku_jarak_kiri_mm', 10, 0, 300),
            'tinggi_baris' => $angka('buku_tinggi_baris_mm', 6, 2, 30),
            'lebar_kolom' => $lebarValid ? $lebar : self::LEBAR_KOLOM,
        ];
    }

    /**
     * Menandai mutasi sudah dicetak, berurutan mulai (halaman, baris) dan pindah halaman otomatis.
     *
     * @param  list<string>  $transaksi
     *
     * @throws ValidationException
     */
    public function catat(Rekening $rekening, array $transaksi, int $halaman, int $baris, ?User $user = null): int
    {
        $perHalaman = $this->tataLetak()['baris_per_halaman'];

        if ($halaman < 1 || $baris < 1 || $baris > $perHalaman) {
            throw ValidationException::withMessages(['baris' => "Baris harus 1 sampai {$perHalaman}."]);
        }

        $urut = Transaksi::query()->where('rekening_id', $rekening->id)->whereKey($transaksi)->orderBy('urut')->pluck('id');

        if ($urut->count() !== count(array_unique($transaksi))) {
            throw ValidationException::withMessages(['transaksi' => 'Ada mutasi yang bukan milik rekening ini.']);
        }

        DB::transaction(function () use ($rekening, $urut, $halaman, $baris, $user) {
            $waktu = now();

            foreach ($urut->values() as $i => $id) {
                CetakBukuTabungan::create([
                    'rekening_id' => $rekening->id,
                    'transaksi_id' => $id,
                    ...$this->geser($halaman, $baris, $i),
                    'user_id' => $user?->id,
                    'created_at' => $waktu,
                ]);
            }

            activity('rekening')->performedOn($rekening)->causedBy($user)->event('cetak_buku')
                ->withProperties(['jumlah' => $urut->count(), 'mulai' => compact('halaman', 'baris')])
                ->log('Mencetak buku tabungan');
        });

        return $urut->count();
    }

    /**
     * Posisi $langkah baris setelah (halaman, baris).
     *
     * @return array{halaman: int, baris: int}
     */
    protected function geser(int $halaman, int $baris, int $langkah): array
    {
        $perHalaman = $this->tataLetak()['baris_per_halaman'];
        $indeks = ($halaman - 1) * $perHalaman + ($baris - 1) + $langkah;

        return ['halaman' => intdiv($indeks, $perHalaman) + 1, 'baris' => $indeks % $perHalaman + 1];
    }

    /**
     * Inisial petugas untuk kolom paraf: huruf pertama tiap kata (maks. 3), atau dua huruf
     * pertama bila namanya satu kata.
     */
    protected function inisial(?string $nama): string
    {
        $kata = array_values(array_filter(preg_split('/\s+/', trim((string) $nama))));

        return match (true) {
            $kata === [] => '',
            count($kata) === 1 => Str::upper(Str::substr($kata[0], 0, 2)),
            default => Str::upper(implode('', array_map(fn ($k) => Str::substr($k, 0, 1), array_slice($kata, 0, 3)))),
        };
    }
}
