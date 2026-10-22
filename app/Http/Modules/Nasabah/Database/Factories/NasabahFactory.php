<?php

namespace App\Http\Modules\Nasabah\Database\Factories;

use App\Http\Modules\Nasabah\Models\Nasabah;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Nasabah>
 */
class NasabahFactory extends Factory
{
    protected $model = Nasabah::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $jenisKelamin = fake()->randomElement(['L', 'P']);

        return [
            'nomor' => fake()->unique()->numerify('TES-######'),
            'nis' => fake()->unique()->numerify('######'),
            'nama' => fake()->name($jenisKelamin === 'L' ? 'male' : 'female'),
            'jenis' => 'siswa',
            'jenis_kelamin' => $jenisKelamin,
            'kelas' => fake()->randomElement(['X', 'XI', 'XII']).' '.fake()->randomElement(['AKL', 'OTKP', 'BDP', 'TKJ']).' '.fake()->numberBetween(1, 3),
            'tempat_lahir' => fake()->city(),
            'tanggal_lahir' => fake()->dateTimeBetween('-18 years', '-15 years')->format('Y-m-d'),
            'alamat' => fake()->address(),
            'no_hp' => fake()->numerify('08##########'),
            'nama_orang_tua' => fake()->name(),
            'is_active' => true,
        ];
    }

    public function nonaktif(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
