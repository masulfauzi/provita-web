<?php

namespace App\Http\Modules\Pinjaman\Models;

use App\Http\Modules\Nasabah\Models\Nasabah;
use App\Http\Modules\Transaksi\Models\Transaksi;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Pinjaman tunai guru/karyawan dengan jasa flat per bulan. Pokok, tenor, dan persen jasa
 * dibekukan saat akad; jadwal angsuran dibuat sekaligus (PinjamanService::jadwal()).
 *
 * @property string $nomor
 * @property string $pokok
 * @property int $tenor
 * @property string $jasa_persen
 * @property string $total_jasa
 * @property string $status
 * @property Carbon $tanggal
 */
class Pinjaman extends Model
{
    use HasUuids, LogsActivity;

    public const BERJALAN = 'berjalan';

    public const LUNAS = 'lunas';

    public const DIBATALKAN = 'dibatalkan';

    public const STATUS = [
        self::BERJALAN => 'Berjalan',
        self::LUNAS => 'Lunas',
        self::DIBATALKAN => 'Dibatalkan',
    ];

    /** Jenis nasabah yang boleh meminjam. */
    public const JENIS_NASABAH = ['guru', 'karyawan'];

    protected $table = 'pinjaman';

    protected $fillable = [
        'nomor',
        'nasabah_id',
        'tanggal',
        'pokok',
        'tenor',
        'jasa_persen',
        'total_jasa',
        'status',
        'keterangan',
        'transaksi_id',
        'dibuat_oleh',
        'lunas_at',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date:Y-m-d',
            'pokok' => 'decimal:2',
            'tenor' => 'integer',
            'jasa_persen' => 'decimal:4',
            'total_jasa' => 'decimal:2',
            'lunas_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Nasabah, $this>
     */
    public function nasabah(): BelongsTo
    {
        return $this->belongsTo(Nasabah::class);
    }

    /**
     * Jadwal angsuran. Tanpa orderBy di relasi agar aman dipakai withSum/withMin;
     * urutkan menurut `ke` saat eager load.
     *
     * @return HasMany<PinjamanAngsuran, $this>
     */
    public function angsuran(): HasMany
    {
        return $this->hasMany(PinjamanAngsuran::class);
    }

    /**
     * Transaksi pencairan.
     *
     * @return BelongsTo<Transaksi, $this>
     */
    public function transaksi(): BelongsTo
    {
        return $this->belongsTo(Transaksi::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    /**
     * Pinjaman asal suatu transaksi pencairan/angsuran (atau pembatalannya). Transaksi angsuran
     * menyimpan nomor pinjaman di keterangannya (transaksi immutable, jadi tetap terlacak).
     */
    public static function dariTransaksi(Transaksi $transaksi): ?self
    {
        $asal = $transaksi->jenis === Transaksi::PEMBALIK ? $transaksi->pembalikDari : $transaksi;

        return match ($asal?->jenis) {
            Transaksi::PINJAMAN_CAIR => self::query()->where('transaksi_id', $asal->id)->first(),
            Transaksi::ANGSURAN => preg_match('/PJ-\d{4}-\d+/', (string) $asal->keterangan, $nomor)
                ? self::query()->where('nomor', $nomor[0])->first()
                : null,
            default => null,
        };
    }

    public function isBerjalan(): bool
    {
        return $this->status === self::BERJALAN;
    }

    public function sisaPokok(): string
    {
        return Money::add('0', ...$this->angsuran->whereNull('dibayar_tanggal')->pluck('pokok')->all());
    }

    public function sisaJasa(): string
    {
        return Money::add('0', ...$this->angsuran->whereNull('dibayar_tanggal')->pluck('jasa')->all());
    }

    public function angsuranBerikutnya(): ?PinjamanAngsuran
    {
        return $this->angsuran->whereNull('dibayar_tanggal')->sortBy('ke')->first();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('pinjaman')
            ->logOnly(['nomor', 'pokok', 'tenor', 'jasa_persen', 'status', 'keterangan'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
