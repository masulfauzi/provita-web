<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alamat terstruktur & kontak wali (dilengkapi nasabah lewat Internet Banking atau petugas).
 * Kolom `alamat` lama tetap ada dan diisi gabungan alamat terstruktur; `nama_orang_tua` = nama wali.
 * Migration pengubah (bukan edit migration awal) karena tabel nasabah produksi sudah berisi data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nasabah', function (Blueprint $table) {
            $table->string('alamat_jalan')->nullable()->after('alamat');
            $table->string('rt', 3)->nullable()->after('alamat_jalan');
            $table->string('rw', 3)->nullable()->after('rt');
            $table->string('kelurahan', 100)->nullable()->after('rw');
            $table->string('kecamatan', 100)->nullable()->after('kelurahan');
            $table->string('kota', 100)->nullable()->after('kecamatan');
            $table->string('kode_pos', 5)->nullable()->after('kota');
            $table->string('wali_hubungan', 10)->nullable()->after('nama_orang_tua'); // ayah | ibu | wali
            $table->string('wali_hp', 20)->nullable()->after('wali_hubungan');
        });
    }

    public function down(): void
    {
        Schema::table('nasabah', function (Blueprint $table) {
            $table->dropColumn(['alamat_jalan', 'rt', 'rw', 'kelurahan', 'kecamatan', 'kota', 'kode_pos', 'wali_hubungan', 'wali_hp']);
        });
    }
};
