<?php

namespace App\Http\Modules\Nasabah\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Modules\Nasabah\Models\Nasabah;
use App\Http\Modules\Nasabah\Requests\StoreNasabahRequest;
use App\Http\Modules\Nasabah\Requests\UpdateNasabahRequest;
use App\Http\Modules\Nasabah\Services\NasabahService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NasabahController extends Controller
{
    public function __construct(protected NasabahService $service) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Nasabah::class);

        return Inertia::render('Nasabah::Index', [
            'items' => $this->service->paginate(
                search: $request->string('search')->toString(),
                filters: $request->only('jenis', 'kelas', 'status'),
                perPage: $request->integer('per_page', 15),
            ),
            'filters' => $request->only('search', 'jenis', 'kelas', 'status'),
            'kelas' => $this->service->daftarKelas(),
            ...$this->options(),
        ]);
    }

    /**
     * Pencarian nasabah aktif untuk AutoComplete (mis. saat membuka rekening).
     */
    public function cari(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Nasabah::class);

        return response()->json($this->service->cariAktif($request->string('q')->toString()));
    }

    public function create(): Response
    {
        Gate::authorize('create', Nasabah::class);

        return Inertia::render('Nasabah::Create', [
            'kelas' => $this->service->daftarKelas(),
            ...$this->options(),
        ]);
    }

    public function store(StoreNasabahRequest $request): RedirectResponse
    {
        $nasabah = $this->service->create($request->validated());

        return redirect()->route('nasabah.show', $nasabah)
            ->with('success', "Nasabah {$nasabah->nama} terdaftar dengan nomor {$nasabah->nomor}.");
    }

    public function show(Nasabah $nasabah): Response
    {
        Gate::authorize('view', $nasabah);

        return Inertia::render('Nasabah::Show', [
            'item' => $nasabah,
            'rekening' => $nasabah->rekening()
                ->with('jenisRekening:id,nama')
                ->orderByRaw("status = 'aktif' desc")
                ->orderBy('nomor')
                ->get(['id', 'nomor', 'jenis_rekening_id', 'saldo', 'status', 'tanggal_buka', 'tanggal_tutup']),
            ...$this->options(),
        ]);
    }

    public function edit(Nasabah $nasabah): Response
    {
        Gate::authorize('update', $nasabah);

        return Inertia::render('Nasabah::Edit', [
            'item' => $nasabah,
            'kelas' => $this->service->daftarKelas(),
            ...$this->options(),
        ]);
    }

    public function update(UpdateNasabahRequest $request, Nasabah $nasabah): RedirectResponse
    {
        $this->service->update($nasabah, $request->validated());

        return redirect()->route('nasabah.show', $nasabah)->with('success', 'Data nasabah berhasil diperbarui.');
    }

    public function toggleActive(Nasabah $nasabah): RedirectResponse
    {
        Gate::authorize('toggleActive', $nasabah);

        $this->service->setActive($nasabah, ! $nasabah->is_active);

        return back()->with('success', $nasabah->is_active
            ? "Nasabah {$nasabah->nama} diaktifkan."
            : "Nasabah {$nasabah->nama} dinonaktifkan.");
    }

    public function destroy(Nasabah $nasabah): RedirectResponse
    {
        Gate::authorize('delete', $nasabah);

        try {
            $this->service->delete($nasabah);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()->route('nasabah.index')->with('success', 'Nasabah berhasil dihapus.');
    }

    /**
     * @return array{jenisOptions: list<array{value: string, label: string}>, jenisKelaminOptions: list<array{value: string, label: string}>}
     */
    protected function options(): array
    {
        $toOptions = fn (array $map) => collect($map)
            ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
            ->values()
            ->all();

        return [
            'jenisOptions' => $toOptions(Nasabah::JENIS),
            'jenisKelaminOptions' => $toOptions(Nasabah::JENIS_KELAMIN),
        ];
    }
}
