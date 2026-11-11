<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kredensial login Internet Banking, terpisah dari data master nasabah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('akun_nasabah', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('nasabah_id')->unique()->constrained('nasabah')->cascadeOnDelete();
            $table->string('password');
            $table->boolean('wajib_ganti_password')->default(true);
            $table->timestamp('profil_lengkap_at')->nullable();
            $table->timestamp('password_diganti_at')->nullable();
            // Naik saat password diganti/direset: sesi dengan versi lama dikeluarkan.
            $table->unsignedInteger('versi_sesi')->default(0);
            $table->rememberToken();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('akun_nasabah');
    }
};
