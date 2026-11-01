<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baris debit/kredit jurnal.
 */
return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('jurnal_detail');
    }
};
