<?php

namespace App\Http\Modules\Pengaturan\Requests;

use App\Http\Modules\Pengaturan\Models\Pengaturan;
use App\Http\Modules\Pengaturan\Services\PengaturanService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;

class UpdatePengaturanRequest extends FormRequest
{
    /** @var Collection<string, Pengaturan>|null */
    protected ?Collection $records = null;

    /**
     * User hanya boleh mengirim kunci dari grup yang boleh ia ubah.
     */
    public function authorize(PengaturanService $service): bool
    {
        if ($this->records()->isEmpty()) {
            return $this->user()->can('pengaturan.lihat');
        }

        return $this->records()->every(fn (Pengaturan $pengaturan) => $service->canEditGroup($this->user(), $pengaturan->grup));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = ['nilai' => ['required', 'array']];

        foreach ($this->records() as $kunci => $pengaturan) {
            $rules["nilai.{$kunci}"] = match ($pengaturan->tipe) {
                'tanggal' => ['required', 'date'],
                'uang' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99', 'decimal:0,2'],
                'persen' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'angka' => ['nullable', 'integer'],
                default => ['nullable', 'string', 'max:255'],
            };
        }

        return $rules;
    }

    public function attributes(): array
    {
        return $this->records()->mapWithKeys(fn (Pengaturan $p) => ["nilai.{$p->kunci}" => strtolower($p->label)])->all();
    }

    /**
     * @return Collection<string, Pengaturan>
     */
    protected function records(): Collection
    {
        $keys = array_keys((array) $this->input('nilai', []));

        return $this->records ??= Pengaturan::whereIn('kunci', $keys)->get()->keyBy('kunci');
    }

    /**
     * Hanya kunci yang dikenal.
     *
     * @return array<string, mixed>
     */
    public function values(): array
    {
        return collect($this->validated('nilai'))->only($this->records()->keys())->all();
    }
}
