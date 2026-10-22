<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rekening menjadi milik nasabah (satu nasabah boleh punya banyak rekening).
 * Nama pemilik diambil dari nasabah, kolom nama dihapus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rekening', function (Blueprint $table) {
            $table->foreignUuid('nasabah_id')->after('nomor')->constrained('nasabah')->restrictOnDelete();
            $table->string('jenis', 20)->default('tabungan')->after('nasabah_id')->index();
            $table->dropColumn('nama');
        });
    }

    public function down(): void
    {
        Schema::table('rekening', function (Blueprint $table) {
            $table->string('nama')->default('');
            $table->dropConstrainedForeignId('nasabah_id');
            $table->dropColumn('jenis');
        });
    }
};
