<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Siswa piket yang menjaga loket pada satu sesi kas. hadir_at = waktu input modal
 * awal, sekaligus presensi siswa piket.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sesi_kas_petugas', function (Blueprint $table) {
            $table->foreignUuid('sesi_kas_id')->constrained('sesi_kas')->cascadeOnDelete();
            $table->foreignUuid('nasabah_id')->constrained('nasabah')->restrictOnDelete();
            $table->timestamp('hadir_at');

            $table->primary(['sesi_kas_id', 'nasabah_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sesi_kas_petugas');
    }
};
