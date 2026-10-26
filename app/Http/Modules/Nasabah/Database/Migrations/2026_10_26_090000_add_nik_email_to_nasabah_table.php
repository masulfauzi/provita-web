<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NIK dan email nasabah, terisi otomatis dari API data siswa sekolah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nasabah', function (Blueprint $table) {
            $table->string('nik', 16)->nullable()->unique()->after('nisn');
            $table->string('email')->nullable()->after('no_hp');
        });
    }

    public function down(): void
    {
        Schema::table('nasabah', function (Blueprint $table) {
            $table->dropUnique(['nik']);
            $table->dropColumn(['nik', 'email']);
        });
    }
};
