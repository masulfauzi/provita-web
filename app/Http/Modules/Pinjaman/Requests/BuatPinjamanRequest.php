<?php

namespace App\Http\Modules\Pinjaman\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BuatPinjamanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('pinjaman.akad');
    }

    /**
     * Jenis nasabah, plafon, tenor maksimal, dan pinjaman berjalan diperiksa oleh PinjamanService.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nasabah_id' => ['required', 'uuid', Rule::exists('nasabah', 'id')],
            'pokok' => ['required', 'integer', 'min:1', 'max:9999999999999'],
            'tenor' => ['required', 'integer', 'min:1', 'max:600'],
            'keterangan' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function attributes(): array
    {
        return [
            'nasabah_id' => 'nasabah',
            'pokok' => 'pokok pinjaman',
            'tenor' => 'jangka waktu',
        ];
    }
}
