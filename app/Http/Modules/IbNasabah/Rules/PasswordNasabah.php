<?php

namespace App\Http\Modules\IbNasabah\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Aturan password Internet Banking (aktivasi & ganti password): minimal 8 karakter, berisi huruf
 * dan angka, berbeda dari password lama, dan bukan salah satu identitas nasabah.
 */
class PasswordNasabah implements ValidationRule
{
    /**
     * @param  list<string>  $terlarang  NIS, NISN, No. nasabah, nomor rekening, No. HP
     */
    public function __construct(protected array $terlarang = [], protected ?string $lama = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = (string) $value;

        match (true) {
            mb_strlen($value) < 8 => $fail('Password minimal 8 karakter.'),
            ! preg_match('/[A-Za-z]/', $value) || ! preg_match('/\d/', $value) => $fail('Password harus mengandung huruf dan angka.'),
            $this->lama !== null && $value === $this->lama => $fail('Password baru tidak boleh sama dengan password lama.'),
            in_array($value, $this->terlarang, true) => $fail('Password tidak boleh sama dengan NIS/NISN, No. HP, atau nomor rekening.'),
            default => null,
        };
    }
}
