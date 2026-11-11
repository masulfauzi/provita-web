<?php

namespace App\Http\Modules\Rekening\Controllers\Ib;

use App\Http\Controllers\Controller;
use App\Http\Modules\IbNasabah\Models\AkunNasabah;
use App\Http\Modules\Pengaturan\Services\PengaturanService;
use App\Http\Modules\Rekening\Models\Rekening;
use App\Http\Modules\Rekening\Resources\RekeningNasabahResource;
use App\Http\Modules\Rekening\Resources\TransaksiNasabahResource;
use App\Http\Modules\Rekening\Services\MutasiNasabahService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Rekening & mutasi milik nasabah yang login (Internet Banking, hanya baca). Rekening yang
 * tidak ada maupun milik nasabah lain sama-sama dijawab 404.
 */
class RekeningNasabahController extends Controller
{
    public function __construct(protected MutasiNasabahService $mutasi) {}

    public function index(Request $request): JsonResponse
    {
        $rekening = Rekening::query()
            ->where('nasabah_id', $this->akun($request)->nasabah_id)
            ->with('jenisRekening:id,kode,nama')
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [Rekening::AKTIF])
            ->orderBy('tanggal_buka')
            ->orderBy('nomor')
            ->get();

        return response()->json([
            'data' => RekeningNasabahResource::collection($rekening),
            'meta' => ['saldo_per' => now()->toIso8601String()],
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        return response()->json([
            'data' => new RekeningNasabahResource($this->milik($request, $id)),
            'meta' => ['saldo_per' => now()->toIso8601String()],
        ]);
    }

    public function mutasi(Request $request, string $id): JsonResponse
    {
        $rekening = $this->milik($request, $id);
        [$dari, $sampai] = $this->mutasi->rentang($request->query());

        $filter = $request->validate([
            'jenis' => ['nullable', 'in:masuk,keluar'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ], ['jenis.in' => 'Jenis harus masuk atau keluar.', 'per_page.max' => 'Maksimal 100 baris per halaman.']);

        $halaman = $this->mutasi->query($rekening, $dari, $sampai, $filter['jenis'] ?? null)
            ->paginate((int) ($filter['per_page'] ?? 25));

        return response()->json([
            'data' => TransaksiNasabahResource::collection($halaman->getCollection()),
            'meta' => [
                'current_page' => $halaman->currentPage(),
                'per_page' => $halaman->perPage(),
                'total' => $halaman->total(),
                'last_page' => $halaman->lastPage(),
                'dari' => $dari->toDateString(),
                'sampai' => $sampai->toDateString(),
            ],
            'ringkasan' => $this->mutasi->ringkasan($rekening, $dari, $sampai),
        ]);
    }

    /**
     * Rekening koran PDF: semua transaksi periode (tanpa filter jenis & paginasi).
     */
    public function pdf(Request $request, string $id, PengaturanService $pengaturan): Response
    {
        $akun = $this->akun($request);
        $rekening = $this->milik($request, $id);
        [$dari, $sampai] = $this->mutasi->rentang($request->query());

        $pdf = Pdf::loadView('ib.rekening-koran', [
            'rekening' => $rekening->load('nasabah:id,nama,nomor,kelas'),
            'transaksi' => $this->mutasi->query($rekening, $dari, $sampai)->reorder()->orderBy('tanggal')->orderBy('urut')->get(),
            'ringkasan' => $this->mutasi->ringkasan($rekening, $dari, $sampai),
            'dari' => $dari,
            'sampai' => $sampai,
            'kop' => [
                'nama_bank' => $pengaturan->get('nama_bank') ?: config('app.name'),
                'nama_sekolah' => $pengaturan->get('nama_sekolah'),
                'alamat_sekolah' => $pengaturan->get('alamat_sekolah'),
            ],
            'dicetak' => now(),
        ])->setPaper('a4');

        activity('nasabah')->performedOn($rekening)->causedBy($akun)->event('unduh_pdf')
            ->withProperties(['dari' => $dari->toDateString(), 'sampai' => $sampai->toDateString()])
            ->log('Rekening koran diunduh');

        $nama = "rekening-koran-{$rekening->nomor}-{$dari->toDateString()}-{$sampai->toDateString()}.pdf";

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$nama}\"",
            'Cache-Control' => 'no-store, private',
        ]);
    }

    protected function akun(Request $request): AkunNasabah
    {
        return $request->user('nasabah');
    }

    protected function milik(Request $request, string $id): Rekening
    {
        return Rekening::query()
            ->where('nasabah_id', $this->akun($request)->nasabah_id)
            ->with('jenisRekening:id,kode,nama')
            ->whereKey($id)
            ->first() ?? abort(404, 'Rekening tidak ditemukan.');
    }
}
