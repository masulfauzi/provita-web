<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NISN (10 digit, nasional) khusus siswa. Unik karena kelak menjadi dasar nomor rekening siswa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nasabah', function (Blueprint $table) {
            $table->string('nisn', 10)->nullable()->unique()->after('nis');
        });
    }

    public function down(): void
    {
        Schema::table('nasabah', function (Blueprint $table) {
            $table->dropUnique(['nisn']);
            $table->dropColumn('nisn');
        });
    }
};
