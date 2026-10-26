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
            'nisn' => ['nullable', 'digits:10', Rule::unique('nasabah', 'nisn')->ignore($this->route('nasabah'))],
            'nik' => ['nullable', 'digits:16', Rule::unique('nasabah', 'nik')->ignore($this->route('nasabah'))],
            'nama' => ['required', 'string', 'max:255'],
            'jenis' => ['required', Rule::in(array_keys(Nasabah::JENIS))],
            'jenis_kelamin' => ['required', Rule::in(array_keys(Nasabah::JENIS_KELAMIN))],
            'kelas' => ['nullable', 'string', 'max:30'],
            'tempat_lahir' => ['nullable', 'string', 'max:100'],
            'tanggal_lahir' => ['nullable', 'date', 'before:today'],
            'alamat' => ['nullable', 'string', 'max:1000'],
            'no_hp' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9 \-]{6,20}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'nama_orang_tua' => ['nullable', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'nis' => 'NIS/NIP',
            'nisn' => 'NISN',
            'nik' => 'NIK',
            'jenis' => 'jenis nasabah',
            'jenis_kelamin' => 'jenis kelamin',
            'tempat_lahir' => 'tempat lahir',
            'tanggal_lahir' => 'tanggal lahir',
            'no_hp' => 'nomor HP',
            'nama_orang_tua' => 'nama orang tua',
        ];
    }

    public function messages(): array
    {
        return [
            'nisn.digits' => 'NISN harus 10 digit angka.',
            'nik.digits' => 'NIK harus 16 digit angka.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // NISN hanya untuk siswa; spasi dibuang agar "0012 345 678" tetap terbaca 10 digit.
        $this->merge([
            'nik' => filled($this->input('nik')) ? preg_replace('/\s+/', '', (string) $this->input('nik')) : null,
            'email' => filled($this->input('email')) ? mb_strtolower(trim((string) $this->input('email'))) : null,
            'nisn' => $this->input('jenis') === 'siswa' && filled($this->input('nisn'))
                ? preg_replace('/\s+/', '', (string) $this->input('nisn'))
                : null,
        ]);

        // Kelas diseragamkan huruf besar supaya filter kelas tidak terpecah ("xi akl 2" vs "XI AKL 2").
        if (filled($this->input('kelas'))) {
            $this->merge(['kelas' => mb_strtoupper(preg_replace('/\s+/', ' ', trim($this->input('kelas'))))]);
        }
    }
}
