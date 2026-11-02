<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambahan modal di tengah sesi, mis. uang dari brankas bendahara saat kas laci
 * tidak cukup untuk penarikan. Seperti modal awal, tidak dijurnal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sesi_kas_tambahan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sesi_kas_id')->constrained('sesi_kas')->cascadeOnDelete();
            $table->decimal('nominal', 15, 2);
            $table->string('keterangan');
            $table->foreignUuid('dicatat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sesi_kas_tambahan');
    }
};
