<?php

namespace App\Http\Modules\Akuntansi\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Modules\Akuntansi\Models\Akun;
use App\Http\Modules\Akuntansi\Requests\SimpanAkunRequest;
use App\Http\Modules\Akuntansi\Services\AkunService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AkunController extends Controller
{
    public function __construct(protected AkunService $service) {}

    public function index(): Response
    {
        Gate::authorize('viewAny', Akun::class);

        return Inertia::render('Akuntansi::Akun', [
            'items' => $this->service->semua(),
            'kategoriOptions' => collect(Akun::KATEGORI)
                ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
                ->values(),
        ]);
    }

    public function store(SimpanAkunRequest $request): RedirectResponse
    {
        $akun = $this->service->create($request->validated());

        return back()->with('success', "Akun {$akun->kode} {$akun->nama} ditambahkan.");
    }

    public function update(SimpanAkunRequest $request, Akun $akun): RedirectResponse
    {
        $this->service->update($akun, $request->validated());

        return back()->with('success', "Akun {$akun->kode} {$akun->nama} diperbarui.");
    }

    public function toggleActive(Akun $akun): RedirectResponse
    {
        Gate::authorize('update', $akun);

        try {
            $this->service->setActive($akun, ! $akun->is_active);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', $akun->is_active ? "Akun {$akun->nama} diaktifkan." : "Akun {$akun->nama} dinonaktifkan.");
    }

    public function destroy(Akun $akun): RedirectResponse
    {
        Gate::authorize('delete', $akun);

        try {
            $this->service->delete($akun);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', 'Akun berhasil dihapus.');
    }
}
