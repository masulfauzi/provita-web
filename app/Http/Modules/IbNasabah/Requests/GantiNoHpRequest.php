<?php

namespace App\Http\Modules\IbNasabah\Requests;

use App\Http\Modules\IbNasabah\Models\AkunNasabah;
use App\Http\Modules\IbNasabah\Services\AkunNasabahService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class GantiNoHpRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $service = app(AkunNasabahService::class);

        $this->merge(collect(['no_hp', 'no_hp_confirmation'])
            ->filter(fn ($field) => is_string($this->input($field)))
            ->mapWithKeys(fn ($field) => [$field => $service->normalisasiHp($this->input($field))])
            ->all());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var AkunNasabah $akun */
        $akun = $this->user('nasabah');

        return [
            'password' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail) use ($akun) {
                if (! Hash::check((string) $value, $akun->password)) {
                    $fail('Password saat ini salah.');
                }
            }],
            'no_hp' => [
                'required', 'string', 'regex:'.SimpanProfilRequest::POLA_HP,
                function (string $attribute, mixed $value, Closure $fail) use ($akun) {
                    if ($value === $akun->no_hp) {
                        $fail('No. HP baru sama dengan No. HP sekarang.');
                    }
                },
                Rule::unique('akun_nasabah', 'no_hp')->ignore($akun->id),
            ],
            'no_hp_confirmation' => ['required', 'same:no_hp'],
        ];
    }

    public function messages(): array
    {
        return [
            'password.required' => 'Password saat ini wajib diisi.',
            'no_hp.required' => 'No. HP baru wajib diisi.',
            'no_hp.regex' => 'Format No. HP tidak valid.',
            'no_hp.unique' => 'No. HP sudah dipakai akun lain.',
            'no_hp_confirmation.required' => 'Ulangi No. HP wajib diisi.',
            'no_hp_confirmation.same' => 'Konfirmasi No. HP tidak sama.',
        ];
    }
}
