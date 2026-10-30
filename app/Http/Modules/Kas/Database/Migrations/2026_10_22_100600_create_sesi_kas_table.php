<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sesi kas teller harian: modal awal saat buka, pencocokan uang fisik saat tutup.
 * Menggantikan pengaturan uang_modal yang dulu hanya satu nilai untuk semua hari.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sesi_kas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Satu sesi per tanggal transaksi; kas dipakai bersama semua teller.
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

        DB::table('pengaturan')->where('kunci', 'uang_modal')->delete();
    }

    public function down(): void
    {
        Schema::dropIfExists('sesi_kas');
    }
};
