<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Pembukuan double-entry: bagan akun, jurnal, dan baris jurnal.
 */
return new class extends Migration
{
    /**
     * Akun bawaan. "peran" menandai akun yang dipakai jurnal otomatis.
     */
    private const AKUN = [
        ['1-101', 'Kas', 'aset', 'kas'],
        ['1-102', 'Bank', 'aset', 'bank'],
        ['1-201', 'Piutang pinjaman', 'aset', null],
        ['2-101', 'Simpanan tabungan nasabah', 'kewajiban', 'simpanan'],
        ['3-101', 'Modal', 'ekuitas', 'modal'],
        ['3-201', 'Laba ditahan', 'ekuitas', 'laba_ditahan'],
        ['4-101', 'Pendapatan administrasi', 'pendapatan', 'pendapatan_administrasi'],
        ['4-102', 'Pendapatan bunga pinjaman', 'pendapatan', null],
        ['5-101', 'Beban bunga tabungan', 'beban', null],
        ['5-102', 'Beban operasional', 'beban', null],
    ];

    public function up(): void
    {
        Schema::create('akun', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode', 20)->unique();
            $table->string('nama', 100);
            $table->string('kategori', 20)->index(); // aset | kewajiban | ekuitas | pendapatan | beban
            $table->string('peran', 40)->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->string('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::create('jurnal', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nomor', 30)->unique();
            $table->date('tanggal')->index();
            $table->string('keterangan');
            $table->string('sumber', 20)->default('manual'); // manual | transaksi
            // Satu transaksi tabungan = satu jurnal.
            $table->foreignUuid('transaksi_id')->nullable()->unique()->constrained('transaksi')->restrictOnDelete();
            // Satu jurnal hanya bisa dibalik satu kali.
            $table->foreignUuid('pembalik_dari_id')->nullable()->unique()->constrained('jurnal')->restrictOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('jurnal_detail', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('jurnal_id')->constrained('jurnal')->cascadeOnDelete();
            $table->foreignUuid('akun_id')->constrained('akun')->restrictOnDelete();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('kredit', 15, 2)->default(0);
            $table->string('keterangan')->nullable();
            $table->unsignedSmallInteger('urut')->default(0);
            $table->timestamps();
        });

        DB::table('akun')->insert(array_map(fn (array $akun) => [
            'id' => (string) Str::uuid(),
            'kode' => $akun[0],
            'nama' => $akun[1],
            'kategori' => $akun[2],
            'peran' => $akun[3],
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], self::AKUN));
    }

    public function down(): void
    {
        Schema::dropIfExists('jurnal_detail');
        Schema::dropIfExists('jurnal');
        Schema::dropIfExists('akun');
    }
};
