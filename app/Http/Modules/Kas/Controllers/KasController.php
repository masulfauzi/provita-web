<?php

namespace App\Http\Modules\Kas\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Modules\Akuntansi\Models\Akun;
use App\Http\Modules\Akuntansi\Models\Jurnal;
use App\Http\Modules\Kas\Requests\SimpanKasRequest;
use App\Http\Modules\Kas\Services\KasService;
use App\Http\Modules\Nasabah\Models\Nasabah;
use App\Http\Modules\Pengaturan\Services\PengaturanService;
use App\Http\Modules\Transaksi\Models\Transaksi;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
     * Satu halaman untuk dua menu; jenis diambil dari default route (kas_masuk/kas_keluar).
     */
    public function index(Request $request): Response
    {
        $jenis = $request->route()->defaults['jenis'];
        abort_unless($request->user()->can($jenis === Transaksi::KAS_MASUK ? 'kas.masuk' : 'kas.keluar'), 403);

        $search = $request->string('search')->toString();
        $filters = $request->only('dari', 'sampai', 'akun_id');

        return Inertia::render('Kas::Index', [
            'jenis' => $jenis,
            'judul' => KasService::JENIS[$jenis][0],
            'items' => $this->service->paginate($jenis, $search, $filters, $request->integer('per_page', 15)),
            'total' => $this->service->total($jenis, $search, $filters),
            'saldoKas' => $this->service->saldoKas(),
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

        $kas = $request->jenis() === Transaksi::KAS_MASUK
            ? $this->service->terima($akun, $nominal, $keterangan, $request->filled('nasabah_id') ? Nasabah::findOrFail($request->validated('nasabah_id')) : null, $request->user())
            : $this->service->keluarkan($akun, $nominal, $keterangan, $request->user());

        return back()->with('success', KasService::JENIS[$kas->jenis][0]." {$kas->nomor} sebesar ".Money::format($kas->nominal).' tercatat.');
    }

    public function show(string $kas): Response
    {
        $kas = $this->temukan($kas)->load([
            'akun:id,kode,nama',
            'nasabah:id,nomor,nama,kelas',
            'user:id,name',
            'pembalik:id,pembalik_dari_id,keterangan,user_id,created_at',
            'pembalik.user:id,name',
        ]);

        return Inertia::render('Kas::Show', [
            'kas' => [
                ...$kas->only('id', 'nomor', 'jenis', 'tanggal', 'nominal', 'keterangan', 'akun', 'nasabah', 'user'),
                'jurnal_id' => Jurnal::query()->where('transaksi_id', $kas->id)->value('id'),
                'batal_at' => $kas->pembalik?->created_at,
                'batal_alasan' => $kas->pembalik?->keterangan,
                'batal_user' => $kas->pembalik?->user,
            ],
            'judul' => KasService::JENIS[$kas->jenis][0],
            'kop' => [
                'nama_bank' => $this->pengaturan->get('nama_bank') ?: config('app.name'),
                'nama_sekolah' => $this->pengaturan->get('nama_sekolah'),
                'alamat_sekolah' => $this->pengaturan->get('alamat_sekolah'),
            ],
        ]);
    }

    public function batal(Request $request, string $kas): RedirectResponse
    {
        $kas = $this->temukan($kas);

        $request->validate(['alasan' => ['required', 'string', 'max:200']]);

        try {
            $this->service->batal($kas, $request->string('alasan')->toString(), $request->user());
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', "{$kas->nomor} dibatalkan.");
    }

    /**
     * Hanya bukti penerimaan/pengeluaran kas; transaksi tabungan dan pembalik tidak bisa dibuka di sini.
     */
    protected function temukan(string $id): Transaksi
    {
        return Transaksi::query()->kasLain()->whereIn('jenis', Transaksi::JENIS_KAS_LAIN)->findOrFail($id);
    }
}
