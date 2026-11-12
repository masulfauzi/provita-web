<?php

namespace App\Http\Modules\IbNasabah\Requests;

use App\Http\Modules\IbNasabah\Models\AkunNasabah;
use App\Http\Modules\IbNasabah\Rules\PasswordNasabah;
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
        $terlarang = app(AkunNasabahService::class)->identitasTerlarang($akun->nasabah, $akun->no_hp);

        return [
            'password_lama' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail) use ($akun) {
                if (! Hash::check((string) $value, $akun->password)) {
                    $fail('Password saat ini salah.');
                }
            }],
            'password' => ['required', 'string', 'max:255', new PasswordNasabah($terlarang, (string) $this->input('password_lama'))],
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
