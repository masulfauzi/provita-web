<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaksi', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('rekening_id')->constrained('rekening')->restrictOnDelete();
            $table->date('tanggal');
            $table->string('jenis', 20); // setor | tarik | pembalik
            $table->decimal('nominal', 15, 2); // selalu positif
            $table->decimal('mutasi', 15, 2); // bertanda: + menambah saldo, - mengurangi
            $table->decimal('saldo_sesudah', 15, 2);
            $table->string('keterangan')->nullable();
            // Satu transaksi hanya bisa dibalik satu kali.
            $table->foreignUuid('pembalik_dari_id')->nullable()->unique()->constrained('transaksi')->restrictOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['rekening_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaksi');
    }
};
