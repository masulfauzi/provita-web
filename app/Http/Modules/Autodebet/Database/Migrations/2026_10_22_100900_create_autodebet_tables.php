<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Autodebet: pindah buku terjadwal (mingguan/bulanan) dari Tabungan Pelajar ke rekening
 * tujuan milik nasabah yang sama, diproses saat sesi kas dibuka.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('autodebet', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('rekening_id')->constrained('rekening')->restrictOnDelete(); // tujuan
            $table->foreignUuid('sumber_rekening_id')->constrained('rekening')->restrictOnDelete();
            $table->decimal('nominal', 15, 2);
            $table->string('periode', 10); // mingguan | bulanan
            // Mingguan: hari 1 (Senin) s.d. 7 (Minggu). Bulanan: tanggal 1-28 agar ada di setiap bulan.
            $table->unsignedTinyInteger('hari');
            $table->date('mulai');
            $table->date('jatuh_tempo')->index();
            $table->decimal('target', 15, 2)->nullable();
            $table->decimal('terkumpul', 15, 2)->default(0);
            $table->string('status', 10)->default('aktif')->index(); // aktif | berhenti | selesai
            $table->string('keterangan')->nullable();
            $table->foreignUuid('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

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
        Schema::dropIfExists('autodebet');
    }
};
