<?php

namespace App\Http\Modules\Nasabah\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Modules\Nasabah\Services\ImporNasabahService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Impor sekali pakai data nasabah, rekening, dan saldo dari sistem lama (Excel).
 * File hanya dibaca dari upload sementara; tidak disimpan di server.
 */
class ImporNasabahController extends Controller
{
    /** Tanggal saldo awal migrasi (tutup buku sistem lama). */
    public const TANGGAL_SALDO_AWAL = '2026-10-30';

    public function __construct(protected ImporNasabahService $service) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Nasabah::Impor', [
            'hasil' => $request->session()->get('impor_hasil'),
            'tanggalDefault' => self::TANGGAL_SALDO_AWAL,
        ]);
    }

    public function periksa(Request $request): RedirectResponse
    {
        $data = $this->validasi($request);
        $hasil = $this->service->periksa($request->file('file')->getRealPath());

        return back()->with('impor_hasil', [
            'file' => $request->file('file')->getClientOriginalName(),
            'tanggal' => $data['tanggal'],
            'ringkasan' => $hasil['ringkasan'],
            'error' => $hasil['error'],
            'peringatan' => $hasil['peringatan'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validasi($request);
        $file = $request->file('file');

        $ringkasan = $this->service->impor($file->getRealPath(), $data['tanggal'], $request->user(), $file->getClientOriginalName());

        return redirect('/nasabah')->with('success', "Impor selesai: {$ringkasan['jumlah_nasabah']} nasabah, {$ringkasan['jumlah_rekening']} rekening, saldo "
            .Money::format($ringkasan['total_saldo']).($ringkasan['jurnal'] ? " (jurnal {$ringkasan['jurnal']})." : '.'));
    }

    /**
     * @return array{tanggal: string}
     */
    protected function validasi(Request $request): array
    {
        return $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx', 'max:5120'],
            'tanggal' => ['required', 'date'],
        ], attributes: ['file' => 'file Excel', 'tanggal' => 'tanggal saldo awal']);
    }
}
