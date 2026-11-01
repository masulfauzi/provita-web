<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mutasi kas: transaksi tabungan per rekening, serta penerimaan/pengeluaran kas
 * (tanpa rekening, akun lawan di akun_id) yang memakai kas yang sama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaksi', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Null untuk penerimaan/pengeluaran kas.
            $table->foreignUuid('rekening_id')->nullable()->constrained('rekening')->restrictOnDelete();
            // Nomor urut mutasi per rekening (baris buku tabungan), diisi di dalam lock rekening.
            $table->unsignedInteger('urut')->default(0);
            // Nomor bukti kas: KM-/KK-YYYY-000001.
            $table->string('nomor', 30)->nullable()->unique();
            $table->date('tanggal');
            $table->string('jenis', 20); // setor | tarik | biaya | penutupan | pembalik | kas_masuk | kas_keluar
            $table->decimal('nominal', 15, 2); // selalu positif
            $table->decimal('mutasi', 15, 2); // bertanda: + menambah saldo, - mengurangi
            $table->decimal('saldo_sesudah', 15, 2); // saldo rekening, atau saldo kas untuk transaksi kas
            $table->string('keterangan')->nullable();
            $table->foreignUuid('akun_id')->nullable()->constrained('akun')->restrictOnDelete();
            $table->foreignUuid('nasabah_id')->nullable()->constrained('nasabah')->restrictOnDelete();
            // Satu transaksi hanya bisa dibalik satu kali.
            $table->foreignUuid('pembalik_dari_id')->nullable()->unique()->constrained('transaksi')->restrictOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['rekening_id', 'urut']);
            $table->index(['rekening_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaksi');
    }
};
