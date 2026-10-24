<?php

namespace App\Http\Modules\Laporan\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Satu lembar Excel dari tabel hasil TabelLaporan::buat(). WithStrictNullComparison
 * agar nominal 0 tetap tertulis 0, bukan sel kosong.
 */
class LaporanExport implements FromArray, ShouldAutoSize, WithColumnFormatting, WithStrictNullComparison, WithStyles, WithTitle
{
    /**
     * @param  array{baris: list<array<int, mixed>>, tebal: list<int>, kolom_angka: list<string>}  $tabel
     */
    public function __construct(protected string $judul, protected array $tabel) {}

    public function array(): array
    {
        return $this->tabel['baris'];
    }

    public function title(): string
    {
        return mb_substr($this->judul, 0, 31);
    }

    public function columnFormats(): array
    {
        return array_fill_keys($this->tabel['kolom_angka'], '#,##0.00;[Red]-#,##0.00');
    }

    public function styles(Worksheet $sheet): array
    {
        return array_fill_keys($this->tabel['tebal'], ['font' => ['bold' => true]]);
    }
}
