<?php

namespace App\Http\Modules\Rekening\Requests;

use App\Http\Modules\Rekening\Models\JenisRekening;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJenisRekeningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', JenisRekening::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(array_filter([
            'kode' => $this->filled('kode') ? strtolower(trim($this->string('kode'))) : null,
            'prefix' => $this->filled('prefix') ? strtoupper(trim($this->string('prefix'))) : null,
        ], fn ($value) => $value !== null));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'kode' => ['required', 'string', 'max:20', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('jenis_rekening', 'kode')],
            ...$this->aturanUmum(),
        ];
    }

    /**
     * Prefix wajib diakhiri tanda "-" agar prefix satu jenis tidak menjadi awalan
     * nomor jenis lain (mis. TA- dan TAB-) saat mencari nomor terakhir.
     *
     * @return array<string, mixed>
     */
    protected function aturanUmum(?string $abaikan = null): array
    {
        return [
            'nama' => ['required', 'string', 'max:100', Rule::unique('jenis_rekening', 'nama')->ignore($abaikan)],
            'prefix' => ['required', 'string', 'max:10', 'regex:/^[A-Z]{2,8}-$/', Rule::unique('jenis_rekening', 'prefix')->ignore($abaikan)],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'kode' => 'kode',
            'nama' => 'nama jenis',
            'prefix' => 'prefix nomor',
        ];
    }

    public function messages(): array
    {
        return [
            'kode.regex' => 'Kode hanya boleh huruf kecil, angka, dan garis bawah, diawali huruf.',
            'prefix.regex' => 'Prefix berisi 2–8 huruf kapital dan diakhiri tanda "-", mis. TAB-.',
        ];
    }
}
