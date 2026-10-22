<?php

namespace App\Http\Modules\Transaksi\Models;

use App\Http\Modules\Rekening\Models\Rekening;
use App\Models\User;
use App\Support\Finance\Immutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Mutasi rekening. Tidak boleh di-update/di-delete (lihat Immutable);
 * koreksi lewat TransaksiService::balik().
 *
 * @property string $nominal
 * @property string $mutasi
 * @property string $saldo_sesudah
 */
class Transaksi extends Model
{
    use HasUuids, Immutable, LogsActivity;

    public const SETOR = 'setor';

    public const TARIK = 'tarik';

    public const PEMBALIK = 'pembalik';

    public const BIAYA = 'biaya';

    public const PENUTUPAN = 'penutupan';

    public const JENIS = [
        self::SETOR => 'Setoran',
        self::TARIK => 'Penarikan',
        self::PEMBALIK => 'Koreksi',
        self::BIAYA => 'Biaya administrasi',
        self::PENUTUPAN => 'Penutupan',
    ];

    protected $table = 'transaksi';

    protected $fillable = [
        'rekening_id',
        'urut',
        'tanggal',
        'jenis',
        'nominal',
        'mutasi',
        'saldo_sesudah',
        'keterangan',
        'pembalik_dari_id',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date:Y-m-d',
            'nominal' => 'decimal:2',
            'mutasi' => 'decimal:2',
            'saldo_sesudah' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Rekening, $this>
     */
    public function rekening(): BelongsTo
    {
        return $this->belongsTo(Rekening::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Transaksi, $this>
     */
    public function pembalikDari(): BelongsTo
    {
        return $this->belongsTo(self::class, 'pembalik_dari_id');
    }

    /**
     * @return HasOne<Transaksi, $this>
     */
    public function pembalik(): HasOne
    {
        return $this->hasOne(self::class, 'pembalik_dari_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('transaksi')
            ->logFillable();
    }
}
