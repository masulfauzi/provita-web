<?php

use App\Http\Modules\Rekening\Models\Rekening;
use App\Http\Modules\Transaksi\Models\Transaksi;
use App\Http\Modules\Transaksi\Services\TransaksiService;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->teller = userWithRole('teller');
    $this->rekening = Rekening::factory()->saldoAwal('100000.00')->create();
});

it('menampilkan halaman setor/tarik dengan rekening terpilih', function () {
    $this->actingAs($this->teller)
        ->get("/transaksi?rekening={$this->rekening->id}&jenis=tarik")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Transaksi::Index')
            ->where('jenis', 'tarik')
            ->where('rekening.id', $this->rekening->id)
            ->where('rekening.saldo', '100000.00'));
});

it('teller menyetor tabungan', function () {
    $this->actingAs($this->teller)
        ->post('/transaksi/setor', ['rekening_id' => $this->rekening->id, 'nominal' => 1250000, 'keterangan' => 'Tabungan bulanan'])
        ->assertRedirect("/rekening/{$this->rekening->id}")
        ->assertSessionHas('success');

    expect($this->rekening->fresh()->saldo)->toBe('1350000.00')
        ->and(Transaksi::latest('urut')->first()->keterangan)->toBe('Tabungan bulanan');
});

it('teller menarik tabungan', function () {
    $this->actingAs($this->teller)
        ->post('/transaksi/tarik', ['rekening_id' => $this->rekening->id, 'nominal' => '40000.50'])
        ->assertRedirect();

    expect($this->rekening->fresh()->saldo)->toBe('59999.50');
});

it('penarikan melebihi saldo ditolak dengan pesan pada nominal', function () {
    $this->actingAs($this->teller)
        ->post('/transaksi/tarik', ['rekening_id' => $this->rekening->id, 'nominal' => 100001])
        ->assertSessionHasErrors('nominal');

    expect($this->rekening->fresh()->saldo)->toBe('100000.00');
});

it('memvalidasi nominal', function (mixed $nominal) {
    $this->actingAs($this->teller)
        ->post('/transaksi/setor', ['rekening_id' => $this->rekening->id, 'nominal' => $nominal])
        ->assertSessionHasErrors('nominal');
})->with([0, -1000, 'abc', '10.123']);

it('teller tidak dapat membuat transaksi pembalik', function () {
    $setoran = app(TransaksiService::class)->setor($this->rekening, 5000);

    $this->actingAs($this->teller)
        ->post("/transaksi/{$setoran->id}/balik", ['alasan' => 'Salah input'])
        ->assertForbidden();
});

it('bendahara membuat transaksi pembalik dengan alasan', function () {
    $setoran = app(TransaksiService::class)->setor($this->rekening, 5000);

    $this->actingAs(userWithRole('bendahara'))
        ->post("/transaksi/{$setoran->id}/balik", ['alasan' => 'Salah input'])
        ->assertSessionHas('success');

    expect($this->rekening->fresh()->saldo)->toBe('100000.00')
        ->and($setoran->pembalik()->value('keterangan'))->toBe('Koreksi: Salah input');
});

it('nomor urut mutasi per rekening berurutan', function () {
    $service = app(TransaksiService::class);
    $service->setor($this->rekening, 1000);
    $service->tarik($this->rekening, 500);
    $service->setor(Rekening::factory()->saldoAwal('0')->create(), 1000);

    expect($this->rekening->transaksi()->orderBy('urut')->pluck('urut')->all())->toBe([1, 2]);
});
