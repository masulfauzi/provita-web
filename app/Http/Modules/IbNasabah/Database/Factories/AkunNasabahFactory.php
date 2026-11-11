<?php

namespace App\Http\Modules\IbNasabah\Database\Factories;

use App\Http\Modules\IbNasabah\Models\AkunNasabah;
use App\Http\Modules\Nasabah\Models\Nasabah;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Default: akun siap pakai (password sudah diganti, profil lengkap), password "Provita2026".
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
            'password' => 'Provita2026',
            'wajib_ganti_password' => false,
            'profil_lengkap_at' => now(),
            'password_diganti_at' => now(),
            'is_active' => true,
        ];
    }

    public function lengkap(): static
    {
        return $this->state(fn () => ['wajib_ganti_password' => false, 'profil_lengkap_at' => now()]);
    }

    public function wajibGantiPassword(): static
    {
        return $this->state(fn () => ['wajib_ganti_password' => true, 'profil_lengkap_at' => null, 'password_diganti_at' => null]);
    }

    public function profilBelumLengkap(): static
    {
        return $this->state(fn () => ['wajib_ganti_password' => false, 'profil_lengkap_at' => null]);
    }
}
