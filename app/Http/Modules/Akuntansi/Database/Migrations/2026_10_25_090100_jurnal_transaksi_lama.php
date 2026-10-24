<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Membuatkan jurnal untuk transaksi tabungan yang sudah ada sebelum modul Akuntansi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Artisan::call('akuntansi:jurnal-transaksi');
    }

    public function down(): void
    {
        $jurnal = DB::table('jurnal')->where('sumber', 'transaksi')->pluck('id');

        DB::table('jurnal_detail')->whereIn('jurnal_id', $jurnal)->delete();
        DB::table('jurnal')->whereIn('id', $jurnal)->delete();
    }
};
