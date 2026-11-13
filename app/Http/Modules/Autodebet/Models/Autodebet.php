<?php

namespace App\Http\Modules\Autodebet\Models;

use App\Http\Modules\Rekening\Models\Rekening;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Instruksi autodebet satu rekening tujuan. Perubahan hanya lewat AutodebetService.
 *
 * @property string $nominal
 * @property string $periode
 * @property int $hari
 * @property Carbon $mulai
 * @property Carbon $jatuh_tempo
 * @property string|null $target saldo rekening tujuan yang ingin dicapai (null = tanpa target)
 * @property string $terkumpul total yang sudah dipindahkan autodebet (informasi)
 * @property string $status
 */
class Autodebet extends Model
{
    use HasUuids, LogsActivity;

    public const MINGGUAN = 'mingguan';

    public const BULANAN = 'bulanan';

    public const PERIODE = [self::MINGGUAN => 'Mingguan', self::BULANAN => 'Bulanan'];

    public const AKTIF = 'aktif';

    public const BERHENTI = 'berhenti';

    public const SELESAI = 'selesai';

    public const STATUS = [self::AKTIF => 'Aktif', self::BERHENTI => 'Dihentikan', self::SELESAI => 'Selesai'];

    public const HARI = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];

    protected $table = 'autodebet';

    protected $fillable = [
        'rekening_id',
        'sumber_rekening_id',
        'nominal',
        'periode',
        'hari',
        'mulai',
        'jatuh_tempo',
        'target',
        'terkumpul',
        'status',
        'keterangan',
        'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'nominal' => 'decimal:2',
            'target' => 'decimal:2',
            'terkumpul' => 'decimal:2',
            'hari' => 'integer',
            'mulai' => 'date:Y-m-d',
            'jatuh_tempo' => 'date:Y-m-d',
        ];
    }

    /**
     * Rekening tujuan (Wisata, Wisuda, dll.).
     *
     * @return BelongsTo<Rekening, $this>
     */
    public function rekening(): BelongsTo
    {
        return $this->belongsTo(Rekening::class);
    }

    /**
     * Rekening sumber dana (Tabungan Pelajar).
     *
     * @return BelongsTo<Rekening, $this>
     */
    public function sumber(): BelongsTo
    {
        return $this->belongsTo(Rekening::class, 'sumber_rekening_id');
    }

    /**
     * @return HasMany<AutodebetRiwayat, $this>
     */
    public function riwayat(): HasMany
    {
        return $this->hasMany(AutodebetRiwayat::class)->orderByDesc('jadwal');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    /**
     * "Tiap Senin" / "Tiap tanggal 5".
     */
    public function jadwalLabel(): string
    {
        return $this->periode === self::MINGGUAN ? 'Tiap '.self::HARI[$this->hari] : "Tiap tanggal {$this->hari}";
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('autodebet')
            ->logOnly(['nominal', 'periode', 'hari', 'target', 'status', 'keterangan'])
            ->logOnlyDirty();
    }
}
