<?php

namespace App\Http\Modules\Kas\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Tambahan modal pada sesi kas yang masih terbuka. Perubahan hanya lewat SesiKasService.
 *
 * @property string $nominal
 * @property string $keterangan
 */
class SesiKasTambahan extends Model
{
    use HasUuids, LogsActivity;

    protected $table = 'sesi_kas_tambahan';

    protected $fillable = ['nominal', 'keterangan', 'dicatat_oleh'];

    protected function casts(): array
    {
        return ['nominal' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<SesiKas, $this>
     */
    public function sesi(): BelongsTo
    {
        return $this->belongsTo(SesiKas::class, 'sesi_kas_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('sesi_kas')
            ->logOnly(['nominal', 'keterangan'])
            ->setDescriptionForEvent(fn (string $event) => match ($event) {
                'created' => 'Tambahan modal dicatat',
                'deleted' => 'Tambahan modal dihapus',
                default => "Tambahan modal {$event}",
            });
    }
}
