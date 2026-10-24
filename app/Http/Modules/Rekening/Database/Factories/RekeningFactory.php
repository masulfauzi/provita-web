<?php

namespace App\Http\Modules\Rekening\Database\Factories;

use App\Http\Modules\Nasabah\Models\Nasabah;
use App\Http\Modules\Rekening\Models\JenisRekening;
use App\Http\Modules\Rekening\Models\Rekening;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rekening>
 */
class RekeningFactory extends Factory
{
    protected $model = Rekening::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nomor' => fake()->unique()->numerify('TES-######'),
            'nasabah_id' => Nasabah::factory(),
            'jenis_rekening_id' => fn () => JenisRekening::kode(JenisRekening::TABUNGAN)->id,
            'saldo_awal' => '0.00',
            'status' => 'aktif',
            'tanggal_buka' => today(),
        ];
    }

    public function saldoAwal(string $saldo): static
    {
        return $this->state(fn () => ['saldo_awal' => $saldo])
            ->afterCreating(fn (Rekening $rekening) => $rekening->forceFill(['saldo' => $saldo])->saveQuietly());
    }
}
