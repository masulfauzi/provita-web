<?php

namespace App\Http\Modules\IbNasabah\Console;

use App\Http\Modules\IbNasabah\Services\AkunNasabahService;
use App\Http\Modules\Nasabah\Models\Nasabah;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Membuat akun Internet Banking untuk nasabah aktif yang belum punya, lalu menyimpan daftar
 * password awal ke Excel (storage/app/private) untuk dibagikan petugas. Password hanya
 * tersimpan sebagai hash di database; file Excel harus dihapus setelah dibagikan.
 */
class BuatAkunNasabah extends Command
{
    protected $signature = 'ib:buat-akun
        {--semua : Semua nasabah aktif yang belum punya akun}
        {--nasabah=* : Nomor nasabah / NIS tertentu}';

    protected $description = 'Membuat akun Internet Banking nasabah dan mengekspor password awal ke Excel';

    public function handle(AkunNasabahService $service): int
    {
        $pilihan = (array) $this->option('nasabah');

        if (! $this->option('semua') && $pilihan === []) {
            $this->components->error('Pilih --semua atau --nasabah=NOMOR.');

            return self::INVALID;
        }

        $nasabah = Nasabah::query()
            ->where('is_active', true)
            ->doesntHave('akunIb')
            ->when($pilihan !== [], fn ($q) => $q->where(fn ($w) => $w->whereIn('nomor', $pilihan)->orWhereIn('nis', $pilihan)))
            ->with('rekening:id,nasabah_id,nomor')
            ->orderBy('kelas')
            ->orderBy('nama')
            ->get();

        if ($nasabah->isEmpty()) {
            $this->components->info('Tidak ada nasabah aktif yang belum punya akun.');

            return self::SUCCESS;
        }

        $baris = [];

        foreach ($nasabah as $item) {
            [, $password] = $service->buatAkun($item);
            $baris[] = [
                $item->nis ?? $item->nomor,
                $item->nama,
                $item->kelas,
                $item->rekening->pluck('nomor')->join(', '),
                $password,
            ];
        }

        $path = 'ib-akun-'.now()->format('Ymd-His').'.xlsx';

        Excel::store(new class($baris) implements FromArray, ShouldAutoSize, WithHeadings
        {
            /** @param  list<array<int, string|null>>  $baris */
            public function __construct(protected array $baris) {}

            public function array(): array
            {
                return $this->baris;
            }

            public function headings(): array
            {
                return ['NIS / No. nasabah', 'Nama', 'Kelas', 'Nomor rekening', 'Password awal'];
            }
        }, $path, 'local');

        activity('nasabah')->event('buat_akun_ib')->withProperties(['jumlah' => count($baris)])
            ->log('Akun Internet Banking dibuat lewat ib:buat-akun');

        $this->components->info(count($baris).' akun dibuat. Daftar password awal: '.storage_path("app/private/{$path}"));
        $this->components->warn('File berisi password awal: bagikan ke nasabah, lalu hapus file tersebut.');

        return self::SUCCESS;
    }
}
