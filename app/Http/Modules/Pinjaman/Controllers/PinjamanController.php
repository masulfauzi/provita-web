<?php

namespace App\Http\Modules\Pinjaman\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Modules\Akuntansi\Models\Jurnal;
use App\Http\Modules\Nasabah\Models\Nasabah;
use App\Http\Modules\Pengaturan\Services\PengaturanService;
use App\Http\Modules\Pinjaman\Models\Pinjaman;
use App\Http\Modules\Pinjaman\Models\PinjamanAngsuran;
use App\Http\Modules\Pinjaman\Requests\BuatPinjamanRequest;
use App\Http\Modules\Pinjaman\Services\PinjamanService;
use App\Http\Modules\Transaksi\Models\Transaksi;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PinjamanController extends Controller
{
    public function __construct(
        protected PinjamanService $service,
        protected PengaturanService $pengaturan,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(array_keys(Pinjaman::STATUS))],
        ]);

        return Inertia::render('Pinjaman::Index', [
            'items' => $this->service->paginate($filters['search'] ?? '', $filters, $request->integer('per_page', 15)),
            'filters' => $filters,
            'statusOptions' => collect(Pinjaman::STATUS)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'tanggal' => $this->pengaturan->tanggalTransaksi()->toDateString(),
        ]);
    }

    public function cariNasabah(Request $request): JsonResponse
    {
        return response()->json($this->service->cariPeminjam($request->string('q')->toString()));
    }

    public function create(): Response
    {
        return Inertia::render('Pinjaman::Create', [
            'tanggal' => $this->pengaturan->tanggalTransaksi()->toDateString(),
            'plafon' => $this->service->plafon(),
            'jasaPersen' => $this->service->jasaPersen(),
            'tenorMaksimal' => $this->service->tenorMaksimal(),
        ]);
    }

    public function store(BuatPinjamanRequest $request): RedirectResponse
    {
        $pinjaman = $this->service->buat(
            Nasabah::findOrFail($request->validated('nasabah_id')),
            (string) $request->validated('pokok'),
            (int) $request->validated('tenor'),
            $request->validated('keterangan'),
            $request->user(),
        );

        return redirect("/pinjaman/{$pinjaman->id}")
            ->with('success', "Pinjaman {$pinjaman->nomor} sebesar ".Money::format($pinjaman->pokok).' dicairkan.');
    }

    public function show(Pinjaman $pinjaman): Response
    {
        $pinjaman->load([
            'nasabah:id,nomor,nama,jenis,nis',
            'pembuat:id,name',
            'angsuran' => fn ($query) => $query->orderBy('ke'),
            'angsuran.transaksi:id,nomor,user_id',
            'angsuran.transaksi.user:id,name',
            'transaksi.pembalik:id,pembalik_dari_id,keterangan,user_id,created_at',
            'transaksi.pembalik.user:id,name',
        ]);

        $hariIni = $this->pengaturan->tanggalTransaksi();

        return Inertia::render('Pinjaman::Show', [
            'pinjaman' => [
                ...$pinjaman->only('id', 'nomor', 'tanggal', 'pokok', 'tenor', 'jasa_persen', 'total_jasa', 'status', 'keterangan', 'lunas_at', 'created_at'),
                'nasabah' => [
                    ...$pinjaman->nasabah->only('id', 'nomor', 'nama', 'nis'),
                    'jenis' => Nasabah::JENIS[$pinjaman->nasabah->jenis] ?? $pinjaman->nasabah->jenis,
                ],
                'pembuat' => $pinjaman->pembuat?->name,
                'sisa_pokok' => $pinjaman->sisaPokok(),
                'sisa_jasa' => $pinjaman->sisaJasa(),
                'jurnal_id' => Jurnal::query()->where('transaksi_id', $pinjaman->transaksi_id)->value('id'),
                'batal_alasan' => $pinjaman->transaksi?->pembalik?->keterangan,
                'batal_at' => $pinjaman->transaksi?->pembalik?->created_at,
                'batal_user' => $pinjaman->transaksi?->pembalik?->user?->name,
            ],
            'angsuran' => $pinjaman->angsuran->map(fn (PinjamanAngsuran $item) => [
                ...$item->only('id', 'ke', 'jatuh_tempo', 'pokok', 'jasa', 'total', 'dibayar_tanggal'),
                'status' => $item->status($hariIni),
                'nomor_bukti' => $item->transaksi?->nomor,
                'petugas' => $item->transaksi?->user?->name,
            ])->all(),
            'riwayat' => $this->riwayat($pinjaman),
            'tanggal' => $hariIni->toDateString(),
        ]);
    }

    public function bayar(Request $request, Pinjaman $pinjaman): RedirectResponse
    {
        $jumlah = (int) $request->validate(['jumlah' => ['required', 'integer', 'min:1', 'max:600']], attributes: ['jumlah' => 'jumlah angsuran'])['jumlah'];

        $transaksi = $this->service->bayar($pinjaman, $jumlah, $request->user());

        return back()->with('success', "Angsuran {$transaksi->nomor} sebesar ".Money::format($transaksi->nominal).' diterima.'
            .($pinjaman->fresh()->status === Pinjaman::LUNAS ? " Pinjaman {$pinjaman->nomor} lunas." : ''));
    }

    public function batalBayar(Request $request, Pinjaman $pinjaman): RedirectResponse
    {
        $alasan = $request->validate(['alasan' => ['required', 'string', 'max:200']])['alasan'];

        $pembalik = $this->service->batalPembayaran($pinjaman, $alasan, $request->user());

        return back()->with('success', 'Pembayaran angsuran sebesar '.Money::format($pembalik->nominal).' dibatalkan.');
    }

    public function batal(Request $request, Pinjaman $pinjaman): RedirectResponse
    {
        $alasan = $request->validate(['alasan' => ['required', 'string', 'max:200']])['alasan'];

        $this->service->batal($pinjaman, $alasan, $request->user());

        return back()->with('success', "Pinjaman {$pinjaman->nomor} dibatalkan; uang pinjaman kembali ke kas.");
    }

    /**
     * Semua transaksi kas pinjaman ini (pencairan, angsuran, dan pembatalannya), terlama dulu.
     *
     * @return list<array<string, mixed>>
     */
    protected function riwayat(Pinjaman $pinjaman): array
    {
        $asal = Transaksi::query()
            ->where('nasabah_id', $pinjaman->nasabah_id)
            ->where(fn ($query) => $query
                ->whereKey($pinjaman->transaksi_id)
                ->orWhere(fn ($q) => $q->where('jenis', Transaksi::ANGSURAN)->where('keterangan', 'like', "%{$pinjaman->nomor}")))
            ->pluck('id');

        return Transaksi::query()
            ->where(fn ($query) => $query->whereIn('id', $asal)->orWhereIn('pembalik_dari_id', $asal))
            ->with(['user:id,name', 'pembalik:id,pembalik_dari_id'])
            ->orderBy('created_at')
            ->get()
            ->map(fn (Transaksi $item) => [
                'id' => $item->id,
                'tanggal' => $item->tanggal->toDateString(),
                'nomor' => $item->nomor,
                'jenis' => $item->jenis === Transaksi::PEMBALIK ? 'Pembatalan' : Transaksi::JENIS[$item->jenis],
                'keterangan' => $item->keterangan,
                'masuk' => Money::isNegative($item->mutasi) ? null : Money::of($item->nominal),
                'keluar' => Money::isNegative($item->mutasi) ? Money::of($item->nominal) : null,
                'dibatalkan' => $item->pembalik !== null,
                'petugas' => $item->user?->name,
            ])
            ->all();
    }
}
