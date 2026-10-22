<?php

namespace App\Http\Modules\Transaksi\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Setoran / penarikan. Otorisasi lewat middleware can:transaksi.setor|tarik di route.
 */
class MutasiRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rekening_id' => ['required', 'uuid', Rule::exists('rekening', 'id')],
            'nominal' => ['required', 'numeric', 'gt:0', 'max:9999999999999.99', 'decimal:0,2'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return ['rekening_id' => 'rekening', 'nominal' => 'nominal'];
    }
}
