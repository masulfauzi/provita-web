<?php

namespace App\Http\Modules\Autodebet\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Modules\Autodebet\Models\Autodebet;
use App\Http\Modules\Autodebet\Services\AutodebetService;
use App\Http\Modules\Pengaturan\Services\PengaturanService;
use App\Http\Modules\Rekening\Models\Rekening;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AutodebetController extends Controller
{
    public function __construct(
        protected AutodebetService $service,
        protected PengaturanService $pengaturan,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->only('search', 'status');

        return Inertia::render('Autodebet::Index', [
            'items' => $this->service->paginate($request->string('search')->toString(), $filters, $request->integer('per_page', 15))
                ->through(fn (Autodebet $item) => $this->ringkas($item)),
            'filters' => $filters,
            'opsi' => $this->opsi(),
            // Dari halaman rekening: langsung buka form untuk rekening ini.
            'rekeningAwal' => $request->filled('rekening')
                ? Rekening::query()->with(['nasabah:id,nomor,nama,kelas', 'jenisRekening:id,nama'])->find($request->string('rekening'))
                    ?->only('id', 'nomor', 'saldo', 'nasabah', 'jenisRekening')
                : null,
        ]);
    }

    public function show(Autodebet $autodebet): Response
    {
        $autodebet->load(['riwayat', 'pembuat:id,name']);

        return Inertia::render('Autodebet::Show', [
            'autodebet' => [...$this->ringkas($autodebet), 'pembuat' => $autodebet->pembuat?->name, 'mulai' => $autodebet->mulai->toDateString()],
            'riwayat' => $autodebet->riwayat->map->only('id', 'jadwal', 'tanggal', 'nominal', 'status', 'alasan'),
            'opsi' => $this->opsi(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'rekening_id' => ['required', 'uuid', 'exists:rekening,id'],
            'sumber_rekening_id' => ['nullable', 'uuid', 'exists:rekening,id'],
            ...$this->aturan(),
            'mulai' => ['nullable', 'date_format:Y-m-d'],
        ], attributes: $this->atribut());

        $autodebet = $this->service->buat($data, $request->user());

        return redirect()->route('autodebet.show', $autodebet)->with('success', 'Autodebet '.Money::format($autodebet->nominal)
            .' '.lcfirst($autodebet->jadwalLabel()).' dibuat. Jatuh tempo pertama '.$autodebet->jatuh_tempo->translatedFormat('j F Y').'.');
    }

    public function update(Request $request, Autodebet $autodebet): RedirectResponse
    {
        $this->service->ubah($autodebet, $request->validate($this->aturan(), attributes: $this->atribut()));

        return back()->with('success', 'Autodebet diperbarui.');
    }

    public function hentikan(Autodebet $autodebet): RedirectResponse
    {
        $this->service->hentikan($autodebet);

        return back()->with('success', 'Autodebet dihentikan.');
    }

    public function lanjutkan(Autodebet $autodebet): RedirectResponse
    {
        $this->service->lanjutkan($autodebet);

        return back()->with('success', 'Autodebet dilanjutkan. Jatuh tempo berikutnya '.$autodebet->jatuh_tempo->translatedFormat('j F Y').'.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function aturan(): array
    {
        return [
            'nominal' => ['required', 'numeric', 'gt:0', 'max:9999999999999.99', 'decimal:0,2'],
            'periode' => ['required', Rule::in(array_keys(Autodebet::PERIODE))],
            'hari' => ['required', 'integer', 'min:1', 'max:28'],
            'target' => ['nullable', 'numeric', 'gt:0', 'max:9999999999999.99', 'decimal:0,2'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function atribut(): array
    {
        return ['rekening_id' => 'rekening tujuan', 'sumber_rekening_id' => 'rekening sumber', 'hari' => 'hari/tanggal'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function ringkas(Autodebet $item): array
    {
        $item->loadMissing(['rekening.nasabah:id,nomor,nama,kelas', 'rekening.jenisRekening:id,nama', 'sumber:id,nomor,saldo']);

        return [
            ...$item->only('id', 'nominal', 'periode', 'hari', 'target', 'terkumpul', 'status', 'keterangan'),
            'jatuh_tempo' => $item->jatuh_tempo->toDateString(),
            'jadwal' => $item->jadwalLabel(),
            'rekening' => $item->rekening?->only('id', 'nomor', 'saldo') + ['jenis' => $item->rekening?->jenisRekening?->nama],
            'nasabah' => $item->rekening?->nasabah?->only('id', 'nomor', 'nama', 'kelas'),
            'sumber' => $item->sumber?->only('id', 'nomor', 'saldo'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function opsi(): array
    {
        $pilihan = fn (array $daftar) => collect($daftar)->map(fn ($label, $value) => compact('value', 'label'))->values();

        return [
            'periode' => $pilihan(Autodebet::PERIODE),
            'hari' => $pilihan(Autodebet::HARI),
            'status' => $pilihan(Autodebet::STATUS),
            'tanggal_transaksi' => $this->pengaturan->tanggalTransaksi()->toDateString(),
        ];
    }
}
