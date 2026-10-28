<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Penerimaan & pengeluaran kas tidak lagi punya tabel sendiri: dicatat di tabel transaksi
 * (kas yang sama dengan uang tabungan nasabah) tanpa rekening, dengan akun lawan sendiri.
 * Pembatalan menjadi transaksi pembalik, seperti koreksi transaksi tabungan.
 */
return new class extends Migration
{
    private const JENIS = ['masuk' => 'kas_masuk', 'keluar' => 'kas_keluar'];

    public function up(): void
    {
        Schema::table('transaksi', function (Blueprint $table) {
            $table->dropForeign(['rekening_id']);
        });

        Schema::table('transaksi', function (Blueprint $table) {
            $table->uuid('rekening_id')->nullable()->change();
            $table->string('nomor', 30)->nullable()->unique()->after('urut');
            $table->foreignUuid('akun_id')->nullable()->after('keterangan')->constrained('akun')->restrictOnDelete();
            $table->foreignUuid('nasabah_id')->nullable()->after('akun_id')->constrained('nasabah')->restrictOnDelete();

            $table->foreign('rekening_id')->references('id')->on('rekening')->restrictOnDelete();
        });

        if (Schema::hasTable('kas')) {
            $this->pindahkanKeTransaksi();
            Schema::drop('kas');
        }
    }

    public function down(): void
    {
        Schema::create('kas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nomor', 30)->unique();
            $table->string('jenis', 10);
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

        $this->kembalikanKeKas();

        Schema::table('transaksi', function (Blueprint $table) {
            $table->dropForeign(['rekening_id']);
            $table->dropForeign(['akun_id']);
            $table->dropForeign(['nasabah_id']);
            $table->dropUnique(['nomor']);
        });

        Schema::table('transaksi', function (Blueprint $table) {
            $table->dropColumn(['nomor', 'akun_id', 'nasabah_id']);
            $table->uuid('rekening_id')->nullable(false)->change();
            $table->foreign('rekening_id')->references('id')->on('rekening')->restrictOnDelete();
        });
    }

    protected function pindahkanKeTransaksi(): void
    {
        $kasAkun = DB::table('akun')->where('peran', 'kas')->value('id');

        // Saldo akun kas sampai dengan jurnal tertentu (nomor jurnal berurutan).
        $saldoKas = fn (string $jurnalId) => (string) DB::table('jurnal_detail as d')
            ->join('jurnal as j', 'j.id', '=', 'd.jurnal_id')
            ->where('d.akun_id', $kasAkun)
            ->where('j.nomor', '<=', DB::table('jurnal')->where('id', $jurnalId)->value('nomor'))
            ->value(DB::raw('COALESCE(SUM(d.debit - d.kredit), 0)'));

        foreach (DB::table('kas')->orderBy('tanggal')->orderBy('nomor')->get() as $kas) {
            $id = (string) Str::uuid();
            $mutasi = $kas->jenis === 'masuk' ? $kas->nominal : '-'.$kas->nominal;

            DB::table('transaksi')->insert([
                'id' => $id,
                'rekening_id' => null,
                'urut' => 0,
                'nomor' => $kas->nomor,
                'tanggal' => $kas->tanggal,
                'jenis' => self::JENIS[$kas->jenis],
                'nominal' => $kas->nominal,
                'mutasi' => $mutasi,
                'saldo_sesudah' => $saldoKas($kas->jurnal_id),
                'keterangan' => $kas->keterangan,
                'akun_id' => $kas->akun_id,
                'nasabah_id' => $kas->nasabah_id,
                'user_id' => $kas->user_id,
                'created_at' => $kas->created_at,
                'updated_at' => $kas->created_at,
            ]);
            DB::table('jurnal')->where('id', $kas->jurnal_id)->update(['sumber' => 'transaksi', 'transaksi_id' => $id]);

            if (! $kas->jurnal_batal_id) {
                continue;
            }

            $pembalik = (string) Str::uuid();
            $tanggalBatal = DB::table('jurnal')->where('id', $kas->jurnal_batal_id)->value('tanggal');

            DB::table('transaksi')->insert([
                'id' => $pembalik,
                'rekening_id' => null,
                'urut' => 0,
                'nomor' => null,
                'tanggal' => $tanggalBatal,
                'jenis' => 'pembalik',
                'nominal' => $kas->nominal,
                'mutasi' => $kas->jenis === 'masuk' ? '-'.$kas->nominal : $kas->nominal,
                'saldo_sesudah' => $saldoKas($kas->jurnal_batal_id),
                'keterangan' => $kas->batal_alasan,
                'akun_id' => $kas->akun_id,
                'pembalik_dari_id' => $id,
                'user_id' => $kas->batal_user_id,
                'created_at' => $kas->batal_at,
                'updated_at' => $kas->batal_at,
            ]);
            DB::table('jurnal')->where('id', $kas->jurnal_batal_id)->update(['sumber' => 'transaksi', 'transaksi_id' => $pembalik]);
        }
    }

    protected function kembalikanKeKas(): void
    {
        $semua = DB::table('transaksi')->whereNull('rekening_id')->get()->keyBy('id');
        $jurnal = DB::table('jurnal')->whereIn('transaksi_id', $semua->keys())->pluck('id', 'transaksi_id');
        $pembalik = $semua->whereNotNull('pembalik_dari_id')->keyBy('pembalik_dari_id');

        foreach ($semua->whereNull('pembalik_dari_id') as $transaksi) {
            $batal = $pembalik->get($transaksi->id);

            DB::table('kas')->insert([
                'id' => (string) Str::uuid(),
                'nomor' => $transaksi->nomor,
                'jenis' => array_search($transaksi->jenis, self::JENIS, true),
                'tanggal' => $transaksi->tanggal,
                'akun_id' => $transaksi->akun_id,
                'nominal' => $transaksi->nominal,
                'keterangan' => $transaksi->keterangan,
                'nasabah_id' => $transaksi->nasabah_id,
                'jurnal_id' => $jurnal[$transaksi->id],
                'user_id' => $transaksi->user_id,
                'batal_at' => $batal?->created_at,
                'batal_alasan' => $batal?->keterangan,
                'batal_user_id' => $batal?->user_id,
                'jurnal_batal_id' => $batal ? $jurnal[$batal->id] : null,
                'created_at' => $transaksi->created_at,
                'updated_at' => $batal->created_at ?? $transaksi->created_at,
            ]);
        }

        DB::table('jurnal')->whereIn('transaksi_id', $semua->keys())->update(['sumber' => 'kas', 'transaksi_id' => null]);
        DB::table('transaksi')->whereNull('rekening_id')->whereNotNull('pembalik_dari_id')->delete();
        DB::table('transaksi')->whereNull('rekening_id')->delete();
    }
};
