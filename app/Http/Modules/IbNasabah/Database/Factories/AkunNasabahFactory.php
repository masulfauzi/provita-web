<?php

namespace App\Http\Modules\IbNasabah\Database\Factories;

use App\Http\Modules\IbNasabah\Models\AkunNasabah;
use App\Http\Modules\Nasabah\Models\Nasabah;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Akun yang sudah diaktivasi, password "Provita2026", No. HP unik 08xxxxxxxxxx.
 *
 * @extends Factory<AkunNasabah>
 */
class AkunNasabahFactory extends Factory
{
    protected $model = AkunNasabah::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nasabah_id' => Nasabah::factory(),
            'no_hp' => '08'.fake()->unique()->numerify('##########'),
            'password' => 'Provita2026',
            'diaktivasi_at' => now(),
            'password_diganti_at' => now(),
            'is_active' => true,
        ];
    }
}
