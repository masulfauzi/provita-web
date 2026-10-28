<?php

namespace App\Http\Modules\Transaksi\Models;

use App\Http\Modules\Akuntansi\Models\Akun;
use App\Http\Modules\Nasabah\Models\Nasabah;
use App\Http\Modules\Rekening\Models\Rekening;
use App\Models\User;
use App\Support\Finance\Immutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Mutasi kas: mutasi rekening tabungan, atau penerimaan/pengeluaran kas lain
 * (rekening kosong, akun lawan di akun_id; saldo_sesudah = saldo kas).
 * Tidak boleh di-update/di-delete (lihat Immutable); koreksi lewat
 * TransaksiService::balik() atau pembatalan di modul Kas.
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

    /** Penerimaan kas di luar tabungan (mis. penjualan buku tabungan); tanpa rekening. */
    public const KAS_MASUK = 'kas_masuk';

    /** Pengeluaran kas untuk kebutuhan bank; tanpa rekening. */
    public const KAS_KELUAR = 'kas_keluar';

    public const JENIS_KAS_LAIN = [self::KAS_MASUK, self::KAS_KELUAR];

    public const JENIS = [
        self::SETOR => 'Setoran',
        self::TARIK => 'Penarikan',
        self::PEMBALIK => 'Koreksi',
        self::BIAYA => 'Biaya administrasi',
        self::PENUTUPAN => 'Penutupan',
        self::KAS_MASUK => 'Penerimaan kas',
        self::KAS_KELUAR => 'Pengeluaran kas',
    ];

    protected $table = 'transaksi';

    protected $fillable = [
        'rekening_id',
        'urut',
        'nomor',
        'tanggal',
        'jenis',
        'nominal',
        'mutasi',
        'saldo_sesudah',
        'keterangan',
        'akun_id',
        'nasabah_id',
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
     * Akun lawan penerimaan/pengeluaran kas.
     *
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

    public function isKasLain(): bool
    {
        return $this->rekening_id === null;
    }

    /**
     * Penerimaan/pengeluaran kas beserta pembatalannya (semua transaksi tanpa rekening).
     *
     * @param  Builder<Transaksi>  $query
     */
    public function scopeKasLain(Builder $query): void
    {
        $query->whereNull('rekening_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('transaksi')
            ->logFillable();
    }
}
