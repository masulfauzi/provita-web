<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nomor urut mutasi per rekening (baris buku tabungan). Diisi di dalam lock
 * rekening sehingga selalu berurutan tanpa celah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaksi', function (Blueprint $table) {
            $table->unsignedInteger('urut')->default(0)->after('rekening_id');
            $table->unique(['rekening_id', 'urut']);
        });
    }

    public function down(): void
    {
        Schema::table('transaksi', function (Blueprint $table) {
            $table->dropUnique(['rekening_id', 'urut']);
            $table->dropColumn('urut');
        });
    }
};
