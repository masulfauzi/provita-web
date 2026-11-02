<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Komputer yang boleh dipakai teller. Browser komputer tersebut memegang token
 * di cookie; yang disimpan di sini hanya hash SHA-256 token itu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perangkat', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nama', 100);
            $table->char('token_hash', 64)->unique();
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('didaftarkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('terakhir_dipakai_at')->nullable();
            $table->string('ip_terakhir', 45)->nullable();
            $table->string('user_agent_terakhir')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perangkat');
    }
};
