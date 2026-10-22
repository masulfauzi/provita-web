<?php

namespace App\Http\Modules\Pengaturan\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Modules\Pengaturan\Models\Pengaturan;
use App\Http\Modules\Pengaturan\Requests\UpdatePengaturanRequest;
use App\Http\Modules\Pengaturan\Services\PengaturanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PengaturanController extends Controller
{
    public function __construct(protected PengaturanService $service) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Pengaturan::class);

        return Inertia::render('Pengaturan::Index', [
            'groups' => $this->service->groupedFor($request->user())->values(),
        ]);
    }

    public function update(UpdatePengaturanRequest $request): RedirectResponse
    {
        $this->service->setMany($request->values());

        return back()->with('success', 'Pengaturan berhasil disimpan.');
    }
}
