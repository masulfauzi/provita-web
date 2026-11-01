<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat cetak buku tabungan: posisi (halaman, baris) setiap mutasi yang sudah dicetak.
 * Transaksi sendiri tidak diubah karena immutable; cetak ulang menambah riwayat baru.
 * Tata letak cetak ada di pengaturan buku_* (PengaturanSeeder).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cetak_buku_tabungan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('rekening_id')->constrained('rekening')->restrictOnDelete();
            $table->foreignUuid('transaksi_id')->constrained('transaksi')->restrictOnDelete();
            $table->unsignedSmallInteger('halaman');
            $table->unsignedSmallInteger('baris');
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['rekening_id', 'transaksi_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cetak_buku_tabungan');
    }
};
