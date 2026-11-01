<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sesi kas harian: modal awal saat buka, pencocokan uang fisik saat tutup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sesi_kas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Satu sesi per tanggal transaksi; kas dipakai bersama semua petugas.
            $table->date('tanggal')->unique();
            $table->decimal('modal_awal', 15, 2);
            $table->foreignUuid('dibuka_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dibuka_at');
            $table->foreignUuid('ditutup_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ditutup_at')->nullable();
            // Snapshot saat tutup: modal awal + pemasukan - pengeluaran.
            $table->decimal('saldo_sistem', 15, 2)->nullable();
            $table->decimal('uang_fisik', 15, 2)->nullable();
            $table->decimal('selisih', 15, 2)->nullable(); // uang fisik - saldo sistem
            $table->string('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sesi_kas');
    }
};
