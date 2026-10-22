<?php

namespace App\Http\Modules\Nasabah\Requests;

class UpdateNasabahRequest extends StoreNasabahRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('nasabah'));
    }
}
