<?php

namespace App\Http\Modules\Pengaturan\Database\Factories;

use App\Http\Modules\Pengaturan\Models\Pengaturan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pengaturan>
 */
class PengaturanFactory extends Factory
{
    protected $model = Pengaturan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kunci' => fake()->unique()->slug(2, false),
            'nilai' => fake()->word(),
            'tipe' => 'teks',
            'label' => fake()->words(2, true),
            'grup' => 'umum',
            'urutan' => 0,
        ];
    }
}
