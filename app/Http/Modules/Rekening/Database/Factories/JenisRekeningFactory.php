<?php

namespace App\Http\Modules\Rekening\Database\Factories;

use App\Http\Modules\Rekening\Models\JenisRekening;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JenisRekening>
 */
class JenisRekeningFactory extends Factory
{
    protected $model = JenisRekening::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $kode = fake()->unique()->lexify('jenis_????');

        return [
            'kode' => $kode,
            'nama' => 'Jenis '.substr($kode, 6),
            'prefix' => fake()->unique()->numerify('9##'),
            'is_active' => true,
        ];
    }

    public function utama(): static
    {
        return $this->state(fn () => ['prefix' => null]);
    }

    public function nonaktif(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
