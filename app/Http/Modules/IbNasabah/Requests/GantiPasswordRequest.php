<?php

namespace App\Http\Modules\IbNasabah\Requests;

use App\Http\Modules\IbNasabah\Models\AkunNasabah;
use App\Http\Modules\IbNasabah\Services\AkunNasabahService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;

class GantiPasswordRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var AkunNasabah $akun */
        $akun = $this->user('nasabah');
        $terlarang = app(AkunNasabahService::class)->identitasTerlarang($akun);

        return [
            'password_lama' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail) use ($akun) {
                if (! Hash::check((string) $value, $akun->password)) {
                    $fail('Password saat ini salah.');
                }
            }],
            'password' => ['required', 'string', 'max:255', function (string $attribute, mixed $value, Closure $fail) use ($terlarang) {
                $value = (string) $value;

                match (true) {
                    mb_strlen($value) < 8 => $fail('Password minimal 8 karakter.'),
                    ! preg_match('/[A-Za-z]/', $value) || ! preg_match('/\d/', $value) => $fail('Password harus mengandung huruf dan angka.'),
                    $value === (string) $this->input('password_lama') => $fail('Password baru tidak boleh sama dengan password lama.'),
                    in_array($value, $terlarang, true) => $fail('Password tidak boleh sama dengan NIS / nomor rekening.'),
                    default => null,
                };
            }],
            'password_confirmation' => ['required', 'same:password'],
        ];
    }

    public function messages(): array
    {
        return [
            'password_lama.required' => 'Password saat ini wajib diisi.',
            'password.required' => 'Password baru wajib diisi.',
            'password_confirmation.required' => 'Konfirmasi password wajib diisi.',
            'password_confirmation.same' => 'Konfirmasi password tidak sama.',
        ];
    }
}
