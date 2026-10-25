<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Penerimaan & pengeluaran kas di luar transaksi tabungan (mis. penjualan buku
 * tabungan, belanja kebutuhan bank). Setiap baris punya jurnal sendiri.
 */
return new class extends Migration
{
    private const AKUN = [
        ['4-103', 'Pendapatan penjualan buku tabungan', 'pendapatan', 'pendapatan_buku_tabungan'],
        ['5-103', 'Beban perlengkapan & ATK', 'beban', null],
    ];

    public function up(): void
    {
        Schema::create('kas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nomor', 30)->unique();
            $table->string('jenis', 10); // masuk | keluar
            $table->date('tanggal');
            $table->foreignUuid('akun_id')->constrained('akun')->restrictOnDelete();
            $table->decimal('nominal', 15, 2);
            $table->string('keterangan');
            $table->foreignUuid('nasabah_id')->nullable()->constrained('nasabah')->restrictOnDelete();
            $table->foreignUuid('jurnal_id')->unique()->constrained('jurnal')->restrictOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('batal_at')->nullable();
            $table->string('batal_alasan')->nullable();
            $table->foreignUuid('batal_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('jurnal_batal_id')->nullable()->unique()->constrained('jurnal')->restrictOnDelete();
            $table->timestamps();

            $table->index(['jenis', 'tanggal']);
        });

        foreach (self::AKUN as [$kode, $nama, $kategori, $peran]) {
            if (DB::table('akun')->where('kode', $kode)->orWhere('nama', $nama)->exists()) {
                continue;
            }

            DB::table('akun')->insert([
                'id' => (string) Str::uuid(),
                'kode' => $kode,
                'nama' => $nama,
                'kategori' => $kategori,
                'peran' => $peran,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (Schema::hasTable('pengaturan') && ! DB::table('pengaturan')->where('kunci', 'harga_buku_tabungan')->exists()) {
            DB::table('pengaturan')->insert([
                'id' => (string) Str::uuid(),
                'kunci' => 'harga_buku_tabungan',
                'label' => 'Harga buku tabungan',
                'tipe' => 'uang',
                'grup' => 'keuangan',
                'nilai' => '0.00',
                'keterangan' => 'Nominal default saat mencatat penjualan buku tabungan di Penerimaan kas.',
                'urutan' => 6,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kas');

        // Akun bawaan hanya dihapus bila belum dipakai jurnal lain.
        DB::table('akun')->whereIn('kode', array_column(self::AKUN, 0))
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))->from('jurnal_detail')->whereColumn('jurnal_detail.akun_id', 'akun.id'))
            ->delete();
    }
};
