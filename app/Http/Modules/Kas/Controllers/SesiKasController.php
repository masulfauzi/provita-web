<?php

namespace App\Http\Modules\Kas\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Modules\Kas\Models\SesiKas;
use App\Http\Modules\Kas\Services\SesiKasService;
use App\Http\Modules\Pengaturan\Services\PengaturanService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SesiKasController extends Controller
{
    public function __construct(
        protected SesiKasService $service,
        protected PengaturanService $pengaturan,
    ) {}

    /**
     * Sesi yang masih terbuka (bisa dari tanggal lain) diutamakan agar segera ditutup;
     * bila tidak ada, tampilkan sesi tanggal transaksi aktif atau form buka sesi.
     */
    public function index(Request $request): Response
    {
        $sesi = $this->service->terbuka() ?? $this->service->hariIni();

        return Inertia::render('Kas::Sesi', [
            'tanggal' => $this->pengaturan->tanggalTransaksi()->toDateString(),
            'rekap' => $sesi ? $this->service->rekap($sesi->tanggal) : null,
            'saranModal' => $this->service->saranModal(),
            'riwayat' => $this->service->riwayat($request->integer('per_page', 10)),
        ]);
    }

    public function buka(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'modal_awal' => ['required', 'numeric', 'min:0', 'max:9999999999999.99', 'decimal:0,2'],
        ], attributes: ['modal_awal' => 'modal awal']);

        $sesi = $this->service->buka((string) $data['modal_awal'], $request->user());

        return back()->with('success', 'Sesi kas '.$sesi->tanggal->translatedFormat('j F Y').' dibuka dengan modal awal '.Money::format($sesi->modal_awal).'.');
    }

    public function tutup(Request $request, SesiKas $sesi): RedirectResponse
    {
        $data = $request->validate([
            'uang_fisik' => ['required', 'numeric', 'min:0', 'max:9999999999999.99', 'decimal:0,2'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ], attributes: ['uang_fisik' => 'uang fisik', 'keterangan' => 'keterangan']);

        $sesi = $this->service->tutup($sesi, (string) $data['uang_fisik'], $data['keterangan'] ?? null, $request->user());

        $hasil = match (Money::compare($sesi->selisih, 0)) {
            0 => 'Uang fisik cocok.',
            1 => 'Uang fisik lebih '.Money::format($sesi->selisih).'.',
            default => 'Uang fisik kurang '.Money::format(Money::negate($sesi->selisih)).'.',
        };

        return back()->with('success', 'Sesi kas '.$sesi->tanggal->translatedFormat('j F Y')." ditutup. {$hasil}");
    }
}
