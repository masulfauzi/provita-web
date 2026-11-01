<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jenis rekening (Tabungan Pelajar, Wisata, dll.). Data bawaan dibuat RekeningSeeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jenis_rekening', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode', 20)->unique();
            $table->string('nama', 100);
            // Prefix angka nomor rekening; null = rekening utama (hanya satu jenis).
            $table->string('prefix', 10)->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->string('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jenis_rekening');
    }
};
