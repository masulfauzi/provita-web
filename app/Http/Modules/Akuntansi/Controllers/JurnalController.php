<?php

namespace App\Http\Modules\Akuntansi\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Modules\Akuntansi\Models\Jurnal;
use App\Http\Modules\Akuntansi\Requests\SimpanJurnalRequest;
use App\Http\Modules\Akuntansi\Services\AkunService;
use App\Http\Modules\Akuntansi\Services\JurnalService;
use App\Http\Modules\Pengaturan\Services\PengaturanService;
use App\Http\Modules\Transaksi\Models\Transaksi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class JurnalController extends Controller
{
    public function __construct(protected JurnalService $service) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Jurnal::class);

        return Inertia::render('Akuntansi::Jurnal/Index', [
            'items' => $this->service->paginate(
                search: $request->string('search')->toString(),
                filters: $request->only('dari', 'sampai', 'sumber'),
                perPage: $request->integer('per_page', 25),
            ),
            'filters' => $request->only('search', 'dari', 'sampai', 'sumber'),
        ]);
    }

    public function create(AkunService $akun, PengaturanService $pengaturan): Response
    {
        Gate::authorize('create', Jurnal::class);

        return Inertia::render('Akuntansi::Jurnal/Create', [
            'akunOptions' => $akun->options(),
            'tanggal' => $pengaturan->tanggalTransaksi()->toDateString(),
        ]);
    }

    public function store(SimpanJurnalRequest $request): RedirectResponse
    {
        $jurnal = $this->service->catat(
            $request->validated('tanggal'),
            $request->validated('keterangan'),
            $request->validated('baris'),
            $request->user(),
        );

        return redirect()->route('jurnal.show', $jurnal)->with('success', "Jurnal {$jurnal->nomor} tersimpan.");
    }

    public function show(Jurnal $jurnal): Response
    {
        Gate::authorize('view', $jurnal);

        $jurnal->load([
            'detail.akun:id,kode,nama,kategori',
            'user:id,name',
            'pembalik:id,pembalik_dari_id,nomor',
            'pembalikDari:id,nomor',
            'transaksi:id,rekening_id,jenis,pembalik_dari_id',
        ]);

        // Bukti kas asal bila jurnal ini berasal dari penerimaan/pengeluaran kas (atau pembatalannya).
        $transaksi = $jurnal->transaksi;
        $kas = $transaksi?->isKasLain()
            ? Transaksi::query()->find($transaksi->pembalik_dari_id ?? $transaksi->id, ['id', 'nomor'])
            : null;

        return Inertia::render('Akuntansi::Jurnal/Show', ['jurnal' => $jurnal, 'kas' => $kas]);
    }

    public function balik(Request $request, Jurnal $jurnal): RedirectResponse
    {
        Gate::authorize('reverse', $jurnal);

        $request->validate(['alasan' => ['required', 'string', 'max:200']]);

        try {
            $pembalik = $this->service->balik($jurnal, $request->user(), $request->string('alasan')->toString());
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()->route('jurnal.show', $pembalik)->with('success', "Jurnal {$jurnal->nomor} dikoreksi dengan {$pembalik->nomor}.");
    }
}
