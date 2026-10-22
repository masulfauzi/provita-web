<?php

namespace App\Http\Modules\Nasabah\Models;

use App\Http\Modules\Nasabah\Database\Factories\NasabahFactory;
use App\Http\Modules\Rekening\Models\Rekening;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property string $nomor
 * @property bool $is_active
 */
class Nasabah extends Model
{
    /** @use HasFactory<NasabahFactory> */
    use HasFactory, HasUuids, LogsActivity;

    public const JENIS = [
        'siswa' => 'Siswa',
        'guru' => 'Guru',
        'karyawan' => 'Karyawan',
    ];

    public const JENIS_KELAMIN = [
        'L' => 'Laki-laki',
        'P' => 'Perempuan',
    ];

    protected $table = 'nasabah';

    protected $fillable = [
        'nis',
        'nama',
        'jenis',
        'jenis_kelamin',
        'kelas',
        'tempat_lahir',
        'tanggal_lahir',
        'alamat',
        'no_hp',
        'nama_orang_tua',
        'is_active',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_lahir' => 'date:Y-m-d',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): NasabahFactory
    {
        return NasabahFactory::new();
    }

    /**
     * @return HasMany<Rekening, $this>
     */
    public function rekening(): HasMany
    {
        return $this->hasMany(Rekening::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('nasabah')
            ->logOnly(['nomor', ...$this->fillable])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
