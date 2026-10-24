<?php

namespace App\Http\Modules\Akuntansi\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SimpanJurnalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('jurnal.tambah');
    }

    /**
     * Keseimbangan debit/kredit & status akun diperiksa oleh JurnalService.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tanggal' => ['required', 'date'],
            'keterangan' => ['required', 'string', 'max:255'],
            'baris' => ['required', 'array', 'min:2', 'max:50'],
            'baris.*.akun_id' => ['required', 'uuid'],
            'baris.*.debit' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99', 'decimal:0,2'],
            'baris.*.kredit' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99', 'decimal:0,2'],
            'baris.*.keterangan' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'baris' => 'baris jurnal',
            'baris.*.akun_id' => 'akun',
            'baris.*.debit' => 'debit',
            'baris.*.kredit' => 'kredit',
        ];
    }
}
