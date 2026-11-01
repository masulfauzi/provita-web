<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bagan akun pembukuan double-entry. Akun bawaan dibuat AkuntansiSeeder; "peran" menandai
 * akun yang dipakai jurnal otomatis. Dibuat sebelum tabel transaksi karena
 * penerimaan/pengeluaran kas menyimpan akun lawannya di transaksi.akun_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('akun', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode', 20)->unique();
            $table->string('nama', 100);
            $table->string('kategori', 20)->index(); // aset | kewajiban | ekuitas | pendapatan | beban
            $table->string('peran', 40)->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->string('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('akun');
    }
};
