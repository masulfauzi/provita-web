<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengaturan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kunci', 100)->unique();
            $table->text('nilai')->nullable();
            $table->string('tipe', 20)->default('teks');
            $table->string('label');
            $table->string('grup', 50)->default('umum')->index();
            $table->string('keterangan')->nullable();
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan');
    }
};
