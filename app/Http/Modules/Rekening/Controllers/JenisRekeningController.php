<?php

namespace App\Http\Modules\Rekening\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Modules\Rekening\Models\JenisRekening;
use App\Http\Modules\Rekening\Requests\StoreJenisRekeningRequest;
use App\Http\Modules\Rekening\Requests\UpdateJenisRekeningRequest;
use App\Http\Modules\Rekening\Services\JenisRekeningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class JenisRekeningController extends Controller
{
    public function __construct(protected JenisRekeningService $service) {}

    public function index(): Response
    {
        Gate::authorize('viewAny', JenisRekening::class);

        return Inertia::render('Rekening::JenisRekening', [
            'items' => $this->service->semua(),
        ]);
    }

    public function store(StoreJenisRekeningRequest $request): RedirectResponse
    {
        $jenis = $this->service->create($request->validated());

        return back()->with('success', "Jenis rekening {$jenis->nama} ditambahkan.");
    }

    public function update(UpdateJenisRekeningRequest $request, JenisRekening $jenisRekening): RedirectResponse
    {
        $this->service->update($jenisRekening, $request->validated());

        return back()->with('success', "Jenis rekening {$jenisRekening->nama} diperbarui.");
    }

    public function toggleActive(JenisRekening $jenisRekening): RedirectResponse
    {
        Gate::authorize('update', $jenisRekening);

        $this->service->setActive($jenisRekening, ! $jenisRekening->is_active);

        return back()->with('success', $jenisRekening->is_active
            ? "Jenis rekening {$jenisRekening->nama} diaktifkan."
            : "Jenis rekening {$jenisRekening->nama} dinonaktifkan; tidak bisa dipilih saat membuka rekening.");
    }

    public function destroy(JenisRekening $jenisRekening): RedirectResponse
    {
        Gate::authorize('delete', $jenisRekening);

        try {
            $this->service->delete($jenisRekening);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', 'Jenis rekening berhasil dihapus.');
    }
}
