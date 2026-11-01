<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hasil pemrosesan setiap jadwal autodebet (berhasil atau gagal).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('autodebet_riwayat', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('autodebet_id')->constrained('autodebet')->cascadeOnDelete();
            $table->date('jadwal'); // jatuh tempo yang diproses
            $table->date('tanggal'); // tanggal transaksi saat diproses
            $table->decimal('nominal', 15, 2);
            $table->string('status', 10); // berhasil | gagal
            $table->string('alasan')->nullable();
            $table->foreignUuid('transaksi_keluar_id')->nullable()->constrained('transaksi')->restrictOnDelete();
            $table->foreignUuid('transaksi_masuk_id')->nullable()->constrained('transaksi')->restrictOnDelete();
            $table->timestamp('created_at')->nullable();

            // Satu jadwal hanya diproses sekali.
            $table->unique(['autodebet_id', 'jadwal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('autodebet_riwayat');
    }
};
