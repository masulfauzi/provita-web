<?php

namespace App\Http\Modules\Nasabah\Models;

use App\Http\Modules\IbNasabah\Models\AkunNasabah;
use App\Http\Modules\Nasabah\Database\Factories\NasabahFactory;
use App\Http\Modules\Rekening\Models\Rekening;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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

    public const LEMBAGA = 'lembaga';

    public const JENIS = [
        'siswa' => 'Siswa',
        'guru' => 'Guru',
        'karyawan' => 'Karyawan',
        // Bukan perorangan (OSIS, koperasi, dll.): tanpa jenis kelamin/NIS/NIK/data lahir;
        // nomor rekening dibuat dari nomor nasabah.
        self::LEMBAGA => 'Lembaga',
    ];

    public const WALI_HUBUNGAN = [
        'ayah' => 'Ayah',
        'ibu' => 'Ibu',
        'wali' => 'Wali',
    ];

    public const JENIS_KELAMIN = [
        'L' => 'Laki-laki',
        'P' => 'Perempuan',
    ];

    protected $table = 'nasabah';

    protected $fillable = [
        'nis',
        'nisn',
        'nik',
        'nama',
        'jenis',
        'jenis_kelamin',
        'kelas',
        'tempat_lahir',
        'tanggal_lahir',
        'alamat',
        'alamat_jalan',
        'rt',
        'rw',
        'kelurahan',
        'kecamatan',
        'kota',
        'kode_pos',
        'no_hp',
        'email',
        'nama_orang_tua',
        'wali_hubungan',
        'wali_hp',
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

    /**
     * Akun Internet Banking (bila sudah dibuat).
     *
     * @return HasOne<AkunNasabah, $this>
     */
    public function akunIb(): HasOne
    {
        return $this->hasOne(AkunNasabah::class);
    }

    /**
     * Gabungan alamat terstruktur untuk kolom `alamat` lama (panel & cetakan), atau null bila
     * alamat jalan kosong.
     *
     * @param  array<string, mixed>  $data
     */
    public static function susunAlamat(array $data): ?string
    {
        if (blank($data['alamat_jalan'] ?? null)) {
            return null;
        }

        $rtRw = array_filter([
            filled($data['rt'] ?? null) ? 'RT '.$data['rt'] : null,
            filled($data['rw'] ?? null) ? 'RW '.$data['rw'] : null,
        ]);

        return implode(', ', array_filter([
            $data['alamat_jalan'],
            $rtRw ? implode('/', $rtRw) : null,
            filled($data['kelurahan'] ?? null) ? 'Kel. '.$data['kelurahan'] : null,
            filled($data['kecamatan'] ?? null) ? 'Kec. '.$data['kecamatan'] : null,
            trim(($data['kota'] ?? '').' '.($data['kode_pos'] ?? '')) ?: null,
        ]));
    }

    public function isLembaga(): bool
    {
        return $this->jenis === self::LEMBAGA;
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
