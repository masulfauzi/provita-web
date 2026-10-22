<?php

namespace App\Http\Modules\Transaksi\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Modules\Rekening\Models\Rekening;
use App\Http\Modules\Rekening\Services\RekeningService;
use App\Http\Modules\Transaksi\Exceptions\TransaksiDitolakException;
use App\Http\Modules\Transaksi\Models\Transaksi;
use App\Http\Modules\Transaksi\Requests\MutasiRequest;
use App\Http\Modules\Transaksi\Services\TransaksiService;
use App\Support\Money;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TransaksiController extends Controller
{
    public function __construct(protected TransaksiService $service) {}

    public function index(Request $request, RekeningService $rekening): Response
    {
        $terpilih = $request->filled('rekening')
            ? Rekening::with('nasabah:id,nomor,nama,kelas')->whereKey($request->string('rekening'))->first()
            : null;

        return Inertia::render('Transaksi::Index', [
            'rekening' => $terpilih ? $rekening->ringkas($terpilih) : null,
            'jenis' => $request->string('jenis')->toString() === 'tarik' ? 'tarik' : 'setor',
        ]);
    }

    public function setor(MutasiRequest $request): RedirectResponse
    {
        return $this->proses($request, fn (array $data) => $this->service->setor(
            $data['rekening_id'], (string) $data['nominal'], $request->user(), $data['keterangan'] ?? null,
        ), 'Setoran');
    }

    public function tarik(MutasiRequest $request): RedirectResponse
    {
        return $this->proses($request, fn (array $data) => $this->service->tarik(
            $data['rekening_id'], (string) $data['nominal'], $request->user(), $data['keterangan'] ?? null,
        ), 'Penarikan');
    }

    public function balik(Request $request, Transaksi $transaksi): RedirectResponse
    {
        $data = $request->validate(['alasan' => ['required', 'string', 'max:255']], attributes: ['alasan' => 'alasan koreksi']);

        try {
            $this->service->balik($transaksi, $request->user(), 'Koreksi: '.$data['alasan']);
        } catch (TransaksiDitolakException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Transaksi pembalik berhasil dicatat.');
    }

    /**
     * @param  Closure(array<string, mixed>): Transaksi  $aksi
     */
    protected function proses(MutasiRequest $request, Closure $aksi, string $label): RedirectResponse
    {
        try {
            $transaksi = $aksi($request->validated());
        } catch (TransaksiDitolakException $e) {
            throw ValidationException::withMessages(['nominal' => $e->getMessage()]);
        }

        return redirect()->route('rekening.show', $transaksi->rekening_id)->with('success', sprintf(
            '%s %s berhasil. Saldo sekarang %s.',
            $label,
            Money::format($transaksi->nominal),
            Money::format($transaksi->saldo_sesudah),
        ));
    }
}
