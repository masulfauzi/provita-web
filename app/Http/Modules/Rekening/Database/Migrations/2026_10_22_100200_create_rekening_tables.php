<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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

        // Jenis bawaan sebagai rekening utama.
        DB::table('jenis_rekening')->insert([
            'id' => (string) Str::uuid(),
            'kode' => 'tabungan',
            'nama' => 'Tabungan',
            'prefix' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('rekening', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nomor', 30)->unique();
            $table->foreignUuid('nasabah_id')->constrained('nasabah')->restrictOnDelete();
            $table->foreignUuid('jenis_rekening_id')->constrained('jenis_rekening')->restrictOnDelete();
            $table->decimal('saldo_awal', 15, 2)->default(0);
            // Cache saldo; sumber kebenaran tetap saldo_awal + mutasi (lihat saldo:rekonsiliasi).
            $table->decimal('saldo', 15, 2)->default(0);
            $table->string('status', 20)->default('aktif');
            $table->date('tanggal_buka')->nullable();
            $table->date('tanggal_tutup')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rekening');
        Schema::dropIfExists('jenis_rekening');
    }
};
