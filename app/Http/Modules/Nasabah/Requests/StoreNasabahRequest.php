<?php

namespace App\Http\Modules\Nasabah\Requests;

use App\Http\Modules\Nasabah\Models\Nasabah;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNasabahRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Nasabah::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nis' => ['nullable', 'string', 'max:30', Rule::unique('nasabah', 'nis')->ignore($this->route('nasabah'))],
            'nama' => ['required', 'string', 'max:255'],
            'jenis' => ['required', Rule::in(array_keys(Nasabah::JENIS))],
            'jenis_kelamin' => ['required', Rule::in(array_keys(Nasabah::JENIS_KELAMIN))],
            'kelas' => ['nullable', 'string', 'max:30'],
            'tempat_lahir' => ['nullable', 'string', 'max:100'],
            'tanggal_lahir' => ['nullable', 'date', 'before:today'],
            'alamat' => ['nullable', 'string', 'max:1000'],
            'no_hp' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9 \-]{6,20}$/'],
            'nama_orang_tua' => ['nullable', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'nis' => 'NIS/NIP',
            'jenis' => 'jenis nasabah',
            'jenis_kelamin' => 'jenis kelamin',
            'tempat_lahir' => 'tempat lahir',
            'tanggal_lahir' => 'tanggal lahir',
            'no_hp' => 'nomor HP',
            'nama_orang_tua' => 'nama orang tua',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Kelas diseragamkan huruf besar supaya filter kelas tidak terpecah ("xi akl 2" vs "XI AKL 2").
        if (filled($this->input('kelas'))) {
            $this->merge(['kelas' => mb_strtoupper(preg_replace('/\s+/', ' ', trim($this->input('kelas'))))]);
        }
    }
}
