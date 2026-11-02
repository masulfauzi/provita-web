<?php

namespace App\Http\Modules\Perangkat\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Komputer terdaftar untuk teller. Dibuat lewat PerangkatService::daftarkan.
 *
 * @property string $nama
 * @property bool $is_active
 * @property Carbon|null $terakhir_dipakai_at
 */
class Perangkat extends Model
{
    use HasUuids, LogsActivity;

    protected $table = 'perangkat';

    protected $fillable = [
        'nama',
        'is_active',
        'terakhir_dipakai_at',
        'ip_terakhir',
        'user_agent_terakhir',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'terakhir_dipakai_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function pendaftar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'didaftarkan_oleh');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('perangkat')
            ->logOnly(['nama', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
