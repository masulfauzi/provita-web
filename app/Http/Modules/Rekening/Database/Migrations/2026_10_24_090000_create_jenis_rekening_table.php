<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Jenis rekening pindah dari konstanta Rekening::JENIS ke tabel agar bisa ditambah
 * dari aplikasi. Rekening yang sudah ada dihubungkan ke jenis lewat kodenya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jenis_rekening', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode', 20)->unique();
            $table->string('nama', 100);
            $table->string('prefix', 10)->unique();
            $table->boolean('is_active')->default(true);
            $table->string('keterangan')->nullable();
            $table->timestamps();
        });

        // Jenis bawaan dibuat di migrasi agar instalasi lama maupun baru langsung memilikinya.
        DB::table('jenis_rekening')->insert([
            'id' => (string) Str::uuid(),
            'kode' => 'tabungan',
            'nama' => 'Tabungan',
            'prefix' => 'TAB-',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('rekening', function (Blueprint $table) {
            $table->uuid('jenis_rekening_id')->nullable()->after('nasabah_id');
        });

        // Kode yang belum dikenal (seharusnya tidak ada) dimasukkan ke tabungan.
        $tabungan = DB::table('jenis_rekening')->where('kode', 'tabungan')->value('id');
        DB::table('jenis_rekening')->pluck('id', 'kode')->each(fn (string $id, string $kode) => DB::table('rekening')
            ->where('jenis', $kode)->update(['jenis_rekening_id' => $id]));
        DB::table('rekening')->whereNull('jenis_rekening_id')->update(['jenis_rekening_id' => $tabungan]);

        Schema::table('rekening', function (Blueprint $table) {
            $table->dropIndex(['jenis']);
            $table->dropColumn('jenis');
        });

        // Foreign key dipasang setelah kolom NOT NULL; MySQL menolak mengubah kolom yang sudah ber-FK.
        Schema::table('rekening', function (Blueprint $table) {
            $table->uuid('jenis_rekening_id')->nullable(false)->change();
        });

        Schema::table('rekening', function (Blueprint $table) {
            $table->foreign('jenis_rekening_id')->references('id')->on('jenis_rekening')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rekening', function (Blueprint $table) {
            $table->string('jenis', 20)->default('tabungan')->after('nasabah_id')->index();
        });

        DB::table('jenis_rekening')->pluck('kode', 'id')->each(fn (string $kode, string $id) => DB::table('rekening')
            ->where('jenis_rekening_id', $id)->update(['jenis' => $kode]));

        Schema::table('rekening', function (Blueprint $table) {
            $table->dropConstrainedForeignId('jenis_rekening_id');
        });

        Schema::dropIfExists('jenis_rekening');
    }
};
