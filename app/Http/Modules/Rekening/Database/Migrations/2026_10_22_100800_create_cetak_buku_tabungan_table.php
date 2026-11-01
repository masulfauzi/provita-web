<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Riwayat cetak buku tabungan: posisi (halaman, baris) setiap mutasi yang sudah
 * dicetak, serta pengaturan tata letak cetak. Transaksi sendiri tidak diubah
 * karena immutable; cetak ulang menambah riwayat baru.
 */
return new class extends Migration
{
    /**
     * [kunci, label, tipe, nilai, keterangan]
     */
    private const PENGATURAN = [
        ['buku_baris_per_halaman', 'Baris per halaman buku tabungan', 'angka', '20', 'Jumlah baris mutasi pada satu halaman buku tabungan.'],
        ['buku_jarak_atas_mm', 'Jarak atas cetak buku (mm)', 'angka', '20', 'Jarak baris pertama dari tepi atas halaman.'],
        ['buku_jarak_kiri_mm', 'Jarak kiri cetak buku (mm)', 'angka', '10', 'Jarak kolom No dari tepi kiri halaman.'],
        ['buku_tinggi_baris_mm', 'Tinggi baris cetak buku (mm)', 'angka', '6', 'Jarak antarbaris pada buku tabungan.'],
        ['buku_lebar_kolom_mm', 'Lebar kolom cetak buku (mm)', 'teks', '10,20,10,30,30,32,14', 'Lebar kolom No, Tanggal, Sandi, Debit, Kredit, Saldo, Paraf; dipisah koma.'],
    ];

    public function up(): void
    {
        Schema::create('cetak_buku_tabungan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('rekening_id')->constrained('rekening')->restrictOnDelete();
            $table->foreignUuid('transaksi_id')->constrained('transaksi')->restrictOnDelete();
            $table->unsignedSmallInteger('halaman');
            $table->unsignedSmallInteger('baris');
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['rekening_id', 'transaksi_id']);
        });

        $urutan = (int) DB::table('pengaturan')->max('urutan');

        foreach (self::PENGATURAN as [$kunci, $label, $tipe, $nilai, $keterangan]) {
            if (DB::table('pengaturan')->where('kunci', $kunci)->exists()) {
                continue;
            }

            DB::table('pengaturan')->insert([
                'id' => (string) Str::uuid(),
                'kunci' => $kunci,
                'label' => $label,
                'tipe' => $tipe,
                'grup' => 'umum',
                'nilai' => $nilai,
                'keterangan' => $keterangan,
                'urutan' => ++$urutan,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cetak_buku_tabungan');
        DB::table('pengaturan')->whereIn('kunci', array_column(self::PENGATURAN, 0))->delete();
    }
};
