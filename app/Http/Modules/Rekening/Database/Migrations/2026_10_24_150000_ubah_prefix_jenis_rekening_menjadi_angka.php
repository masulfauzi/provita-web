<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Prefix jenis rekening menjadi angka (atau kosong untuk rekening utama) karena
 * nomor rekening kini = prefix + NISN/NIS nasabah. Nomor rekening lama tidak diubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jenis_rekening', function (Blueprint $table) {
            $table->string('prefix', 10)->nullable()->change();
        });

        DB::table('jenis_rekening')->where('kode', 'tabungan')->update(['prefix' => null]);

        // Jenis lain yang prefix-nya masih huruf diberi angka berikutnya yang belum terpakai.
        $terpakai = DB::table('jenis_rekening')->whereNotNull('prefix')->pluck('prefix')
            ->filter(fn (string $prefix) => ctype_digit($prefix))
            ->all();
        $angka = 1;

        DB::table('jenis_rekening')
            ->whereNotNull('prefix')
            ->orderBy('created_at')
            ->get(['id', 'prefix'])
            ->reject(fn ($jenis) => ctype_digit($jenis->prefix))
            ->each(function ($jenis) use (&$terpakai, &$angka) {
                while (in_array((string) $angka, $terpakai, true)) {
                    $angka++;
                }

                DB::table('jenis_rekening')->where('id', $jenis->id)->update(['prefix' => (string) $angka]);
                $terpakai[] = (string) $angka;
            });
    }

    public function down(): void
    {
        DB::table('jenis_rekening')->where('kode', 'tabungan')->update(['prefix' => 'TAB-']);
        DB::table('jenis_rekening')->where('kode', '!=', 'tabungan')->get(['id', 'prefix'])
            ->each(fn ($jenis) => DB::table('jenis_rekening')->where('id', $jenis->id)
                ->update(['prefix' => 'J'.preg_replace('/\D/', '', (string) $jenis->prefix).'X-']));

        Schema::table('jenis_rekening', function (Blueprint $table) {
            $table->string('prefix', 10)->nullable(false)->change();
        });
    }
};
