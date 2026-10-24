<?php

namespace App\Http\Modules\Rekening\Requests;

class UpdateJenisRekeningRequest extends StoreJenisRekeningRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('jenisRekening'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->aturanUmum($this->route('jenisRekening')->id);
    }
}
