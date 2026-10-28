<?php

namespace App\Http\Modules\Kas\Requests;

use App\Http\Modules\Transaksi\Models\Transaksi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SimpanKasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->jenis() === Transaksi::KAS_MASUK ? 'kas.masuk' : 'kas.keluar');
    }

    /**
     * Kategori akun yang sesuai jenis & status aktif diperiksa oleh KasService.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'akun_id' => ['required', 'uuid', Rule::exists('akun', 'id')],
            'nominal' => ['required', 'numeric', 'gt:0', 'max:9999999999999.99', 'decimal:0,2'],
            'keterangan' => ['required', 'string', 'max:200'],
            'nasabah_id' => ['nullable', 'uuid', Rule::exists('nasabah', 'id'), Rule::prohibitedIf($this->jenis() === Transaksi::KAS_KELUAR)],
        ];
    }

    public function jenis(): string
    {
        return $this->route()->defaults['jenis'];
    }

    public function attributes(): array
    {
        return [
            'akun_id' => 'kategori',
            'nasabah_id' => 'nasabah',
        ];
    }
}
