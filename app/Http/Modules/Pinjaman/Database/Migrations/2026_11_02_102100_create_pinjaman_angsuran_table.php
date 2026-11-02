<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jadwal angsuran per bulan, dibuat sekaligus saat akad. Baris yang dibayar
 * menyimpan tanggal bayar dan transaksi angsurannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pinjaman_angsuran', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('pinjaman_id')->constrained('pinjaman')->cascadeOnDelete();
            $table->unsignedSmallInteger('ke');
            $table->date('jatuh_tempo');
            $table->decimal('pokok', 15, 2);
            $table->decimal('jasa', 15, 2);
            $table->decimal('total', 15, 2);
            $table->date('dibayar_tanggal')->nullable();
            $table->foreignUuid('transaksi_id')->nullable()->constrained('transaksi')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['pinjaman_id', 'ke']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pinjaman_angsuran');
    }
};
