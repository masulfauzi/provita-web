<?php

namespace App\Http\Modules\Kas\Models;

use App\Http\Modules\Akuntansi\Models\Akun;
use App\Http\Modules\Akuntansi\Models\Jurnal;
use App\Http\Modules\Nasabah\Models\Nasabah;
use App\Models\User;
use App\Support\Finance\ImmutableRecordException;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Penerimaan / pengeluaran kas. Tidak bisa diubah atau dihapus; satu-satunya
 * perubahan yang diizinkan adalah pembatalan (kolom batal_*).
 *
 * @property string $nomor
 * @property string $jenis
 * @property string $nominal
 * @property Carbon|null $batal_at
 */
class Kas extends Model
{
    use HasUuids;

    public const MASUK = 'masuk';

    public const KELUAR = 'keluar';

    /**
     * jenis => [judul menu, prefix nomor, kategori akun lawan].
     */
    public const JENIS = [
        self::MASUK => ['Penerimaan kas', 'KM-', 'pendapatan'],
        self::KELUAR => ['Pengeluaran kas', 'KK-', 'beban'],
    ];

    protected const KOLOM_BATAL = ['batal_at', 'batal_alasan', 'batal_user_id', 'jurnal_batal_id', 'updated_at'];

    protected $table = 'kas';

    protected $fillable = [
        'nomor',
        'jenis',
        'tanggal',
        'akun_id',
        'nominal',
        'keterangan',
        'nasabah_id',
        'jurnal_id',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date:Y-m-d',
            'nominal' => 'decimal:2',
            'batal_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $kas) {
            if (array_diff(array_keys($kas->getDirty()), self::KOLOM_BATAL)) {
                throw ImmutableRecordException::forModel($kas, 'update');
            }
        });

        static::deleting(fn (self $kas) => throw ImmutableRecordException::forModel($kas, 'hapus'));
    }

    public function dibatalkan(): bool
    {
        return $this->batal_at !== null;
    }

    /**
     * @return BelongsTo<Akun, $this>
     */
    public function akun(): BelongsTo
    {
        return $this->belongsTo(Akun::class);
    }

    /**
     * @return BelongsTo<Nasabah, $this>
     */
    public function nasabah(): BelongsTo
    {
        return $this->belongsTo(Nasabah::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function batalUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'batal_user_id');
    }

    /**
     * @return BelongsTo<Jurnal, $this>
     */
    public function jurnal(): BelongsTo
    {
        return $this->belongsTo(Jurnal::class);
    }

    /**
     * @return BelongsTo<Jurnal, $this>
     */
    public function jurnalBatal(): BelongsTo
    {
        return $this->belongsTo(Jurnal::class, 'jurnal_batal_id');
    }
}
