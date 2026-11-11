<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat login Internet Banking (berhasil maupun gagal); 50 entri terakhir per akun disimpan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_nasabah', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('akun_nasabah_id')->constrained('akun_nasabah')->cascadeOnDelete();
            $table->timestamp('waktu');
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('perangkat', 100)->nullable();
            $table->boolean('berhasil');

            $table->index(['akun_nasabah_id', 'waktu']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_nasabah');
    }
};
