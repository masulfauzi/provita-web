<?php

namespace App\Http\Modules\Akuntansi\Requests;

use App\Http\Modules\Akuntansi\Models\Akun;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SimpanAkunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('akun.kelola');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $akun = $this->route('akun');

        return [
            'kode' => ['required', 'string', 'max:20', 'regex:/^[0-9][0-9.\-]*$/', Rule::unique('akun', 'kode')->ignore($akun)],
            'nama' => ['required', 'string', 'max:100', Rule::unique('akun', 'nama')->ignore($akun)],
            'kategori' => ['required', Rule::in(array_keys(Akun::KATEGORI))],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return ['kode' => 'kode akun', 'nama' => 'nama akun'];
    }

    public function messages(): array
    {
        return ['kode.regex' => 'Kode akun diawali angka dan hanya berisi angka, titik, atau tanda "-", mis. 5-103.'];
    }
}
