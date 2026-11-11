<?php

namespace App\Http\Modules\IbNasabah\Requests;

use App\Http\Modules\IbNasabah\Models\AkunNasabah;
use App\Http\Modules\Nasabah\Models\Nasabah;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SimpanProfilRequest extends FormRequest
{
    /** Field yang boleh diubah nasabah; nama, NIS, kelas, dan jenis tidak bisa diubah lewat IB. */
    public const FIELD = [
        'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'alamat_jalan', 'rt', 'rw', 'kelurahan',
        'kecamatan', 'kota', 'kode_pos', 'no_hp', 'email', 'wali_nama', 'wali_hubungan', 'wali_hp',
    ];

    public const POLA_HP = '/^08\d{8,11}$/';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var AkunNasabah $akun */
        $akun = $this->user('nasabah');
        $wali = $akun->nasabah->jenis === 'siswa' ? 'required' : 'nullable';

        return [
            'tempat_lahir' => ['required', 'string', 'max:100'],
            'tanggal_lahir' => ['required', 'date_format:Y-m-d', 'before:today'],
            'jenis_kelamin' => ['required', Rule::in(array_keys(Nasabah::JENIS_KELAMIN))],
            'alamat_jalan' => ['required', 'string', 'max:255'],
            'rt' => ['required', 'regex:/^\d{1,3}$/'],
            'rw' => ['required', 'regex:/^\d{1,3}$/'],
            'kelurahan' => ['required', 'string', 'max:100'],
            'kecamatan' => ['required', 'string', 'max:100'],
            'kota' => ['required', 'string', 'max:100'],
            'kode_pos' => ['required', 'regex:/^\d{5}$/'],
            'no_hp' => ['required', 'regex:'.self::POLA_HP],
            'email' => ['nullable', 'email', 'max:255'],
            'wali_nama' => [$wali, 'string', 'max:255'],
            'wali_hubungan' => [$wali, Rule::in(array_keys(Nasabah::WALI_HUBUNGAN))],
            'wali_hp' => [$wali, 'regex:'.self::POLA_HP],
            'persetujuan' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':Attribute wajib diisi.',
            'max' => ':Attribute maksimal :max karakter.',
            'tanggal_lahir.date_format' => 'Format tanggal lahir harus YYYY-MM-DD.',
            'tanggal_lahir.before' => 'Tanggal lahir harus sebelum hari ini.',
            'jenis_kelamin.in' => 'Jenis kelamin harus L atau P.',
            'rt.regex' => 'RT berisi 1–3 angka.',
            'rw.regex' => 'RW berisi 1–3 angka.',
            'kode_pos.regex' => 'Kode pos berisi 5 angka.',
            'no_hp.regex' => 'Format No. HP tidak valid.',
            'wali_hp.regex' => 'Format No. HP tidak valid.',
            'wali_hubungan.in' => 'Hubungan wali harus ayah, ibu, atau wali.',
            'email.email' => 'Format email tidak valid.',
            'persetujuan.accepted' => 'Persetujuan wajib dicentang.',
        ];
    }

    public function attributes(): array
    {
        return [
            'tempat_lahir' => 'tempat lahir',
            'tanggal_lahir' => 'tanggal lahir',
            'jenis_kelamin' => 'jenis kelamin',
            'alamat_jalan' => 'alamat',
            'rt' => 'RT',
            'rw' => 'RW',
            'kota' => 'kota/kabupaten',
            'kode_pos' => 'kode pos',
            'no_hp' => 'No. HP',
            'wali_nama' => 'nama orang tua/wali',
            'wali_hubungan' => 'hubungan',
            'wali_hp' => 'No. HP orang tua/wali',
        ];
    }
}
