<?php

namespace App\Http\Modules\Rekening\Requests;

use App\Http\Modules\Rekening\Models\Rekening;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BukaRekeningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Rekening::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nasabah_id' => ['required', 'uuid', Rule::exists('nasabah', 'id')],
            'jenis' => ['required', Rule::in(array_keys(Rekening::JENIS))],
            'setoran_awal' => ['required', 'numeric', 'gt:0', 'max:9999999999999.99', 'decimal:0,2'],
        ];
    }

    public function attributes(): array
    {
        return [
            'nasabah_id' => 'nasabah',
            'jenis' => 'jenis rekening',
            'setoran_awal' => 'setoran awal',
        ];
    }
}
