<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jurnal pembukuan double-entry (header); barisnya di jurnal_detail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jurnal', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nomor', 30)->unique();
            $table->date('tanggal')->index();
            $table->string('keterangan');
            $table->string('sumber', 20)->default('manual'); // manual | transaksi
            // Satu transaksi (tabungan atau kas) = satu jurnal.
            $table->foreignUuid('transaksi_id')->nullable()->unique()->constrained('transaksi')->restrictOnDelete();
            // Satu jurnal hanya bisa dibalik satu kali.
            $table->foreignUuid('pembalik_dari_id')->nullable()->unique()->constrained('jurnal')->restrictOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jurnal');
    }
};
