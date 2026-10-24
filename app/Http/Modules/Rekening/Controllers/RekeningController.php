<?php

namespace App\Http\Modules\Rekening\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Modules\Nasabah\Models\Nasabah;
use App\Http\Modules\Pengaturan\Services\PengaturanService;
use App\Http\Modules\Rekening\Models\JenisRekening;
use App\Http\Modules\Rekening\Models\Rekening;
use App\Http\Modules\Rekening\Requests\BukaRekeningRequest;
use App\Http\Modules\Rekening\Services\JenisRekeningService;
use App\Http\Modules\Rekening\Services\RekeningService;
use App\Http\Modules\Transaksi\Exceptions\TransaksiDitolakException;
use App\Http\Modules\Transaksi\Models\Transaksi;
use App\Http\Modules\Transaksi\Services\TransaksiService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RekeningController extends Controller
{
    public function __construct(
        protected RekeningService $service,
        protected TransaksiService $transaksi,
        protected JenisRekeningService $jenis,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Rekening::class);

        return Inertia::render('Rekening::Index', [
            'items' => $this->service->paginate(
                search: $request->string('search')->toString(),
                filters: $request->only('jenis_rekening_id', 'status'),
                perPage: $request->integer('per_page', 15),
            ),
            'filters' => $request->only('search', 'jenis_rekening_id', 'status'),
            'jenisOptions' => $this->jenis->options(),
        ]);
    }

    public function cari(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Rekening::class);

        return response()->json($this->service->cariAktif($request->string('q')->toString()));
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Rekening::class);

        $nasabah = $request->filled('nasabah')
            ? Nasabah::query()->whereKey($request->string('nasabah'))->first(['id', 'nomor', 'nama', 'kelas', 'is_active'])
            : null;

        return Inertia::render('Rekening::Create', [
            'nasabah' => $nasabah,
            'jenisOptions' => $this->jenis->options(hanyaAktif: true),
            'setoranAwalMinimal' => app(PengaturanService::class)->uang('setoran_awal_minimal'),
        ]);
    }

    public function store(BukaRekeningRequest $request): RedirectResponse
    {
        $rekening = $this->service->buka(
            Nasabah::findOrFail($request->validated('nasabah_id')),
            JenisRekening::findOrFail($request->validated('jenis_rekening_id')),
            (string) $request->validated('setoran_awal'),
            $request->user(),
        );

        return redirect()->route('rekening.show', $rekening)
            ->with('success', "Rekening {$rekening->nomor} dibuka dengan setoran awal ".Money::format($rekening->saldo).'.');
    }

    public function show(Request $request, Rekening $rekening): Response
    {
        Gate::authorize('view', $rekening);

        $rekening->load(['nasabah:id,nomor,nama,nis,kelas,jenis,is_active', 'jenisRekening:id,kode,nama']);

        return Inertia::render('Rekening::Show', [
            'rekening' => $rekening,
            'mutasi' => $this->transaksi->bukuTabungan($rekening, $request->integer('per_page', 25))
                ->through(fn (Transaksi $t) => [
                    ...$t->only('id', 'urut', 'tanggal', 'jenis', 'nominal', 'mutasi', 'saldo_sesudah', 'keterangan', 'pembalik_dari_id', 'created_at'),
                    'petugas' => $t->user?->name,
                    'sudah_dibalik' => $t->pembalik !== null,
                ]),
            'jenisTransaksi' => Transaksi::JENIS,
            'penutupan' => $rekening->isAktif() ? [
                'biaya' => $this->transaksi->biayaPenutupan($rekening->saldo),
                'diserahkan' => Money::sub($rekening->saldo, $this->transaksi->biayaPenutupan($rekening->saldo)),
            ] : null,
        ]);
    }

    public function tutup(Request $request, Rekening $rekening): RedirectResponse
    {
        Gate::authorize('close', $rekening);

        try {
            $hasil = $this->service->tutup($rekening, $request->user());
        } catch (TransaksiDitolakException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Rekening {$rekening->nomor} ditutup. Uang yang diserahkan ke nasabah: "
            .Money::format($hasil['diserahkan']).' (biaya administrasi '.Money::format($hasil['biaya']).').');
    }
}
