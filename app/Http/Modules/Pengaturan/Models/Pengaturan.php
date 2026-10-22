<?php

namespace App\Http\Modules\Pengaturan\Models;

use App\Http\Modules\Pengaturan\Database\Factories\PengaturanFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Pengaturan aplikasi berbentuk key-value (setara sheet M di workbook lama).
 *
 * @property string $kunci
 * @property string|null $nilai
 * @property string $tipe tanggal|uang|persen|angka|teks
 * @property string $grup harian|keuangan|umum
 */
class Pengaturan extends Model
{
    /** @use HasFactory<PengaturanFactory> */
    use HasFactory, HasUuids, LogsActivity;

    public const TIPE = ['tanggal', 'uang', 'persen', 'angka', 'teks'];

    /**
     * Grup pengaturan => permission yang dibutuhkan untuk mengubahnya.
     */
    public const GRUP = [
        'harian' => 'pengaturan.ubah_harian',
        'keuangan' => 'pengaturan.ubah_keuangan',
        'umum' => 'pengaturan.ubah_umum',
    ];

    protected $table = 'pengaturan';

    protected $fillable = [
        'kunci',
        'nilai',
        'tipe',
        'label',
        'grup',
        'keterangan',
        'urutan',
    ];

    protected static function newFactory(): PengaturanFactory
    {
        return PengaturanFactory::new();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('pengaturan')
            ->logOnly(['kunci', 'nilai'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
