<?php

namespace App\Http\Modules\IbNasabah\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $waktu
 * @property bool $berhasil
 */
class LoginNasabah extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'login_nasabah';

    protected $fillable = ['akun_nasabah_id', 'waktu', 'ip', 'user_agent', 'perangkat', 'berhasil'];

    protected function casts(): array
    {
        return [
            'waktu' => 'datetime',
            'berhasil' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<AkunNasabah, $this>
     */
    public function akun(): BelongsTo
    {
        return $this->belongsTo(AkunNasabah::class, 'akun_nasabah_id');
    }
}
