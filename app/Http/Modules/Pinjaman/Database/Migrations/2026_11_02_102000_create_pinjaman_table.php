<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Akad pinjaman guru/karyawan. Pokok, tenor, dan persen jasa dibekukan saat akad;
 * uang dicairkan tunai lewat transaksi pinjaman_cair.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pinjaman', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nomor', 30)->unique(); // PJ-2026-000001, sama dengan nomor bukti pencairan
            $table->foreignUuid('nasabah_id')->constrained('nasabah')->restrictOnDelete();
            $table->date('tanggal');
            $table->decimal('pokok', 15, 2);
            $table->unsignedSmallInteger('tenor'); // bulan
            $table->decimal('jasa_persen', 7, 4); // flat per bulan dari pokok
            $table->decimal('total_jasa', 15, 2);
            $table->string('status', 20)->default('berjalan')->index(); // berjalan | lunas | dibatalkan
            $table->string('keterangan')->nullable();
            $table->foreignUuid('transaksi_id')->nullable()->constrained('transaksi')->restrictOnDelete();
            $table->foreignUuid('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('lunas_at')->nullable();
            $table->timestamps();

            $table->index(['nasabah_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pinjaman');
    }
};
