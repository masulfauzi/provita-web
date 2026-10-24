<?php

namespace App\Http\Modules\Rekening\Requests;

use App\Http\Modules\Rekening\Models\JenisRekening;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreJenisRekeningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', JenisRekening::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'kode' => $this->filled('kode') ? strtolower(trim($this->string('kode'))) : $this->input('kode'),
            'prefix' => $this->filled('prefix') ? trim($this->string('prefix')) : null,
        ]);
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
     * Prefix berupa angka yang ditaruh di depan NISN/NIS nasabah.
     *
     * @return array<string, mixed>
     */
    protected function aturanUmum(?string $abaikan = null): array
    {
        return [
            'nama' => ['required', 'string', 'max:100', Rule::unique('jenis_rekening', 'nama')->ignore($abaikan)],
            'prefix' => ['nullable', 'regex:/^[0-9]{1,5}$/', Rule::unique('jenis_rekening', 'prefix')->ignore($abaikan)],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Prefix kosong menandai rekening utama dan hanya boleh dimiliki satu jenis.
     *
     * @return list<callable>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($this->input('prefix') !== null) {
                return;
            }

            $utama = JenisRekening::query()
                ->whereNull('prefix')
                ->when($this->route('jenisRekening'), fn ($query, JenisRekening $jenis) => $query->whereKeyNot($jenis->id))
                ->value('nama');

            if ($utama) {
                $validator->errors()->add('prefix', "Rekening utama (tanpa prefix) sudah ada: {$utama}. Isi prefix angka.");
            }
        }];
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
            'prefix.regex' => 'Prefix hanya boleh angka (1–5 digit), mis. 2.',
        ];
    }
}
