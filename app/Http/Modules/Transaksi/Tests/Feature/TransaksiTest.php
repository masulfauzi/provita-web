<?php

use App\Http\Modules\Pengaturan\Database\Seeders\PengaturanSeeder;
use App\Http\Modules\Pengaturan\Services\PengaturanService;
use App\Http\Modules\Rekening\Models\Rekening;
use App\Http\Modules\Transaksi\Exceptions\TransaksiDitolakException;
use App\Http\Modules\Transaksi\Models\Transaksi;
use App\Http\Modules\Transaksi\Services\TransaksiService;
use App\Support\Finance\ImmutableRecordException;

beforeEach(function () {
    $this->service = app(TransaksiService::class);
    $this->rekening = Rekening::factory()->saldoAwal('100000.00')->create();
});

it('setoran menambah saldo', function () {
    $transaksi = $this->service->setor($this->rekening, 50000);

    expect($transaksi->mutasi)->toBe('50000.00')
        ->and($transaksi->saldo_sesudah)->toBe('150000.00')
        ->and($this->rekening->fresh()->saldo)->toBe('150000.00');
});

it('penarikan mengurangi saldo', function () {
    $this->service->tarik($this->rekening, '30000.50');

    expect($this->rekening->fresh()->saldo)->toBe('69999.50');
});

it('menolak penarikan melebihi saldo', function () {
    $this->service->tarik($this->rekening, 100001);
})->throws(TransaksiDitolakException::class, 'Saldo tidak cukup');

it('menolak nominal nol atau negatif', function (int|string $nominal) {
    $this->service->setor($this->rekening, $nominal);
})->with([0, -5000, '0.00'])->throws(TransaksiDitolakException::class);

it('dua penarikan berurutan tidak membuat saldo negatif', function () {
    $this->service->tarik($this->rekening, 70000);

    expect(fn () => $this->service->tarik($this->rekening, 70000))->toThrow(TransaksiDitolakException::class);
    expect($this->rekening->fresh()->saldo)->toBe('30000.00');
});

it('transaksi tidak bisa diubah', function () {
    $transaksi = $this->service->setor($this->rekening, 50000);

    $transaksi->update(['nominal' => '1.00']);
})->throws(ImmutableRecordException::class);

it('transaksi tidak bisa dihapus', function () {
    $transaksi = $this->service->setor($this->rekening, 50000);

    $transaksi->delete();
})->throws(ImmutableRecordException::class);

it('koreksi dilakukan dengan transaksi pembalik', function () {
    $setoran = $this->service->setor($this->rekening, 50000);
    $pembalik = $this->service->balik($setoran, alasan: 'Salah input');

    expect($pembalik->jenis)->toBe(Transaksi::PEMBALIK)
        ->and($pembalik->mutasi)->toBe('-50000.00')
        ->and($pembalik->pembalik_dari_id)->toBe($setoran->id)
        ->and($this->rekening->fresh()->saldo)->toBe('100000.00')
        ->and(Transaksi::count())->toBe(2);
});

it('transaksi hanya bisa dibalik satu kali', function () {
    $setoran = $this->service->setor($this->rekening, 50000);
    $this->service->balik($setoran);

    $this->service->balik($setoran);
})->throws(TransaksiDitolakException::class, 'sudah pernah dibalik');

it('transaksi pembalik tidak bisa dibalik', function () {
    $pembalik = $this->service->balik($this->service->setor($this->rekening, 50000));

    $this->service->balik($pembalik);
})->throws(TransaksiDitolakException::class);

it('memakai tanggal transaksi aktif dari pengaturan', function () {
    $this->seed(PengaturanSeeder::class);
    app(PengaturanService::class)->set('tanggal_transaksi', '2026-07-15');

    $transaksi = $this->service->setor($this->rekening, 1000);

    expect($transaksi->tanggal->toDateString())->toBe('2026-07-15');
});

it('mencatat transaksi ke log aktivitas', function () {
    $this->service->setor($this->rekening, 50000);

    $this->assertDatabaseHas('activity_log', ['log_name' => 'transaksi', 'event' => 'created']);
});

it('rekonsiliasi mendeteksi dan memperbaiki saldo cache yang salah', function () {
    $this->service->setor($this->rekening, 25000);
    $this->rekening->forceFill(['saldo' => '1.00'])->saveQuietly();

    $this->artisan('saldo:rekonsiliasi')->assertFailed();
    $this->artisan('saldo:rekonsiliasi --fix')->assertSuccessful();

    expect($this->rekening->fresh()->saldo)->toBe('125000.00');
    $this->artisan('saldo:rekonsiliasi')->assertSuccessful();
});
