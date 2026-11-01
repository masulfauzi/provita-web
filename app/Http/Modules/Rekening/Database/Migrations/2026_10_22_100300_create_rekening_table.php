<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rekening tabungan nasabah; nomor = prefix jenis + NISN/NIS/NIP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rekening', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nomor', 30)->unique();
            $table->foreignUuid('nasabah_id')->constrained('nasabah')->restrictOnDelete();
            $table->foreignUuid('jenis_rekening_id')->constrained('jenis_rekening')->restrictOnDelete();
            $table->decimal('saldo_awal', 15, 2)->default(0);
            // Cache saldo; sumber kebenaran tetap saldo_awal + mutasi (lihat saldo:rekonsiliasi).
            $table->decimal('saldo', 15, 2)->default(0);
            $table->string('status', 20)->default('aktif');
            $table->date('tanggal_buka')->nullable();
            $table->date('tanggal_tutup')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rekening');
    }
};
