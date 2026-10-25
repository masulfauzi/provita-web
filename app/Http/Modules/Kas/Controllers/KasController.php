<?php

namespace App\Http\Modules\Kas\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Modules\Akuntansi\Models\Akun;
use App\Http\Modules\Kas\Models\Kas;
use App\Http\Modules\Kas\Requests\SimpanKasRequest;
use App\Http\Modules\Kas\Services\KasService;
use App\Http\Modules\Nasabah\Models\Nasabah;
use App\Http\Modules\Pengaturan\Services\PengaturanService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class KasController extends Controller
{
    public function __construct(
        protected KasService $service,
        protected PengaturanService $pengaturan,
    ) {}

    /**
     * Satu halaman untuk dua menu; jenis diambil dari default route (masuk/keluar).
     */
    public function index(Request $request): Response
    {
        $jenis = $request->route()->defaults['jenis'];
        Gate::authorize('create', [Kas::class, $jenis]);

        $search = $request->string('search')->toString();
        $filters = $request->only('dari', 'sampai', 'akun_id');

        return Inertia::render('Kas::Index', [
            'jenis' => $jenis,
            'judul' => Kas::JENIS[$jenis][0],
            'items' => $this->service->paginate($jenis, $search, $filters, $request->integer('per_page', 15)),
            'total' => $this->service->total($jenis, $search, $filters),
            'filters' => $request->only('search', 'dari', 'sampai', 'akun_id'),
            'akunOptions' => $this->service->akunOptions($jenis),
            'hargaBukuTabungan' => $this->pengaturan->uang('harga_buku_tabungan'),
        ]);
    }

    public function store(SimpanKasRequest $request): RedirectResponse
    {
        $akun = Akun::findOrFail($request->validated('akun_id'));
        $nominal = (string) $request->validated('nominal');
        $keterangan = $request->validated('keterangan');

        $kas = $request->jenis() === Kas::MASUK
            ? $this->service->terima($akun, $nominal, $keterangan, $request->filled('nasabah_id') ? Nasabah::findOrFail($request->validated('nasabah_id')) : null, $request->user())
            : $this->service->keluarkan($akun, $nominal, $keterangan, $request->user());

        return back()->with('success', Kas::JENIS[$kas->jenis][0]." {$kas->nomor} sebesar ".Money::format($kas->nominal).' tercatat.');
    }

    public function show(Kas $kas): Response
    {
        Gate::authorize('view', $kas);

        $kas->load(['akun:id,kode,nama', 'nasabah:id,nomor,nama,kelas', 'user:id,name', 'batalUser:id,name', 'jurnal:id,nomor', 'jurnalBatal:id,nomor']);

        return Inertia::render('Kas::Show', [
            'kas' => $kas,
            'judul' => Kas::JENIS[$kas->jenis][0],
            'kop' => [
                'nama_bank' => $this->pengaturan->get('nama_bank') ?: config('app.name'),
                'nama_sekolah' => $this->pengaturan->get('nama_sekolah'),
                'alamat_sekolah' => $this->pengaturan->get('alamat_sekolah'),
            ],
        ]);
    }

    public function batal(Request $request, Kas $kas): RedirectResponse
    {
        Gate::authorize('cancel', $kas);

        $request->validate(['alasan' => ['required', 'string', 'max:200']]);

        try {
            $this->service->batal($kas, $request->string('alasan')->toString(), $request->user());
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', "{$kas->nomor} dibatalkan.");
    }
}
