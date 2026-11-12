<?php

namespace App\Http\Modules\IbNasabah\Requests;

use App\Http\Modules\IbNasabah\Controllers\AktivasiNasabahController;
use App\Http\Modules\IbNasabah\Rules\PasswordNasabah;
use App\Http\Modules\IbNasabah\Services\AkunNasabahService;
use App\Http\Modules\Nasabah\Models\Nasabah;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Carbon;

/**
 * Langkah 2 aktivasi: data pribadi + No. HP (ID login) + password. Hanya untuk nasabah yang sudah
 * lolos POST /nasabah/aktivasi/cek dalam sesi yang sama dan belum kedaluwarsa.
 */
class AktivasiRequest extends SimpanProfilRequest
{
    protected ?Nasabah $nasabahAktivasi = null;

    public function authorize(): bool
    {
        $sesi = $this->session()->get(AktivasiNasabahController::KUNCI_SESI);

        if (! is_array($sesi) || Carbon::parse($sesi['berlaku_sampai'])->isPast()) {
            return false;
        }

        $this->nasabahAktivasi = Nasabah::query()->whereKey($sesi['nasabah_id'])->where('is_active', true)->first();

        return $this->nasabahAktivasi !== null;
    }

    protected function failedAuthorization(): void
    {
        $this->session()->forget(AktivasiNasabahController::KUNCI_SESI);

        throw new HttpResponseException(response()->json([
            'message' => 'Sesi aktivasi tidak ditemukan atau sudah kedaluwarsa. Ulangi verifikasi NISN/No. nasabah dan nomor rekening.',
            'kode' => 'VERIFIKASI_DIPERLUKAN',
        ], 403));
    }

    public function nasabah(): Nasabah
    {
        return $this->nasabahAktivasi;
    }

    protected function prepareForValidation(): void
    {
        $service = app(AkunNasabahService::class);

        $this->merge(collect(['no_hp', 'no_hp_confirmation'])
            ->filter(fn ($field) => is_string($this->input($field)))
            ->mapWithKeys(fn ($field) => [$field => $service->normalisasiHp($this->input($field))])
            ->all());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $terlarang = app(AkunNasabahService::class)->identitasTerlarang($this->nasabahAktivasi, (string) $this->input('no_hp'));

        return [
            ...parent::rules(),
            'no_hp' => ['required', 'string', 'regex:'.self::POLA_HP, 'unique:akun_nasabah,no_hp'],
            'no_hp_confirmation' => ['required', 'same:no_hp'],
            'password' => ['required', 'string', 'max:255', new PasswordNasabah($terlarang)],
            'password_confirmation' => ['required', 'same:password'],
            'persetujuan' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'no_hp.regex' => 'Format No. HP tidak valid.',
            'no_hp.unique' => 'No. HP sudah dipakai akun lain.',
            'no_hp_confirmation.required' => 'Ulangi No. HP wajib diisi.',
            'no_hp_confirmation.same' => 'Konfirmasi No. HP tidak sama.',
            'password_confirmation.required' => 'Konfirmasi password wajib diisi.',
            'password_confirmation.same' => 'Konfirmasi password tidak sama.',
            'persetujuan.accepted' => 'Persetujuan wajib dicentang.',
        ];
    }

    public function attributes(): array
    {
        return [...parent::attributes(), 'no_hp' => 'No. HP', 'password' => 'password'];
    }

    protected function wajibWali(): bool
    {
        return $this->nasabahAktivasi->jenis === 'siswa';
    }
}
