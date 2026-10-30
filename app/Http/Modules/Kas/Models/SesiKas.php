<?php

namespace App\Http\Modules\Kas\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Sesi kas harian. Dibuka dengan modal awal, ditutup sekali dengan uang fisik;
 * perubahan hanya lewat SesiKasService.
 *
 * @property Carbon $tanggal
 * @property string $modal_awal
 * @property Carbon|null $ditutup_at
 * @property string|null $saldo_sistem
 * @property string|null $uang_fisik
 * @property string|null $selisih
 */
class SesiKas extends Model
{
    use HasUuids, LogsActivity;

    protected $table = 'sesi_kas';

    protected $fillable = [
        'tanggal',
        'modal_awal',
        'dibuka_oleh',
        'dibuka_at',
        'ditutup_oleh',
        'ditutup_at',
        'saldo_sistem',
        'uang_fisik',
        'selisih',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date:Y-m-d',
            'modal_awal' => 'decimal:2',
            'saldo_sistem' => 'decimal:2',
            'uang_fisik' => 'decimal:2',
            'selisih' => 'decimal:2',
            'dibuka_at' => 'datetime',
            'ditutup_at' => 'datetime',
        ];
    }

    public function isDitutup(): bool
    {
        return $this->ditutup_at !== null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function pembuka(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuka_oleh');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function penutup(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditutup_oleh');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('sesi_kas')
            ->logFillable()
            ->logOnlyDirty();
    }
}
