<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nasabah', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nomor', 20)->unique();
            $table->string('nis', 30)->nullable()->unique();
            // Dasar nomor rekening siswa (selain siswa memakai NIS/NIP).
            $table->string('nisn', 10)->nullable()->unique();
            $table->string('nik', 16)->nullable()->unique();
            $table->string('nama')->index();
            $table->string('jenis', 20)->default('siswa'); // siswa | guru | karyawan
            $table->char('jenis_kelamin', 1); // L | P
            $table->string('kelas', 30)->nullable()->index();
            $table->string('tempat_lahir', 100)->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->text('alamat')->nullable();
            $table->string('no_hp', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('nama_orang_tua')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nasabah');
    }
};
