<?php

namespace App\Http\Modules\IbNasabah\Models;

use App\Http\Modules\IbNasabah\Database\Factories\AkunNasabahFactory;
use App\Http\Modules\Nasabah\Models\Nasabah;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;

/**
 * Akun login Internet Banking nasabah (guard `nasabah`). Satu nasabah satu akun, dibuat sendiri
 * oleh nasabah lewat aktivasi; login dengan No. HP + password.
 *
 * @property string $nasabah_id
 * @property string $no_hp
 * @property Carbon|null $diaktivasi_at
 * @property int $versi_sesi
 * @property bool $is_active
 */
class AkunNasabah extends Authenticatable
{
    /** @use HasFactory<AkunNasabahFactory> */
    use HasFactory, HasUuids;

    protected $table = 'akun_nasabah';

    protected $fillable = [
        'nasabah_id',
        'no_hp',
        'password',
        'diaktivasi_at',
        'password_diganti_at',
        'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    /** Sama dengan default kolom, agar model yang baru dibuat langsung punya nilainya. */
    protected $attributes = [
        'versi_sesi' => 0,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'diaktivasi_at' => 'datetime',
            'password_diganti_at' => 'datetime',
            'versi_sesi' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): AkunNasabahFactory
    {
        return AkunNasabahFactory::new();
    }

    /**
     * @return BelongsTo<Nasabah, $this>
     */
    public function nasabah(): BelongsTo
    {
        return $this->belongsTo(Nasabah::class);
    }

    /**
     * @return HasMany<LoginNasabah, $this>
     */
    public function riwayatLogin(): HasMany
    {
        return $this->hasMany(LoginNasabah::class);
    }

    /**
     * Akun dan nasabahnya sama-sama aktif.
     */
    public function bisaLogin(): bool
    {
        return $this->is_active && (bool) $this->nasabah?->is_active;
    }

    /**
     * Keluarkan semua sesi yang sedang berjalan (dipakai saat password diganti/direset).
     */
    public function naikkanVersiSesi(): void
    {
        $this->forceFill(['versi_sesi' => (int) $this->versi_sesi + 1])->save();
    }
}
