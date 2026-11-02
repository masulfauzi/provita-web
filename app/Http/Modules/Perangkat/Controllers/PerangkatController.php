<?php

namespace App\Http\Modules\Perangkat\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Modules\Perangkat\Models\Perangkat;
use App\Http\Modules\Perangkat\Services\PerangkatService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PerangkatController extends Controller
{
    public function __construct(protected PerangkatService $service) {}

    public function index(Request $request): Response
    {
        $ini = $this->service->dariRequest($request);

        return Inertia::render('Perangkat::Index', [
            'items' => Perangkat::with('pendaftar:id,name')
                ->orderByDesc('is_active')->orderBy('nama')
                ->get()
                ->map(fn (Perangkat $p) => [
                    'id' => $p->id,
                    'nama' => $p->nama,
                    'is_active' => $p->is_active,
                    'terakhir_dipakai_at' => $p->terakhir_dipakai_at,
                    'ip_terakhir' => $p->ip_terakhir,
                    'user_agent_terakhir' => $p->user_agent_terakhir,
                    'didaftarkan_oleh' => $p->pendaftar?->name,
                    'created_at' => $p->created_at,
                    'ini' => $p->id === $ini?->id,
                ]),
            'perangkatIni' => $ini?->only('id', 'nama'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['nama' => ['required', 'string', 'max:100']], attributes: ['nama' => 'nama komputer']);

        [$perangkat, $cookie] = $this->service->daftarkan($data['nama'], $request->user(), $this->service->dariRequest($request));

        return back()->withCookie($cookie)->with('success', "Komputer ini terdaftar sebagai \"{$perangkat->nama}\". Teller kini bisa login di sini.");
    }

    public function update(Request $request, Perangkat $perangkat): RedirectResponse
    {
        $data = $request->validate(['nama' => ['required', 'string', 'max:100']], attributes: ['nama' => 'nama komputer']);

        $perangkat->update($data);

        return back()->with('success', 'Nama komputer diperbarui.');
    }

    public function toggle(Perangkat $perangkat): RedirectResponse
    {
        $perangkat->update(['is_active' => ! $perangkat->is_active]);

        return back()->with('success', $perangkat->is_active
            ? "\"{$perangkat->nama}\" diaktifkan kembali."
            : "\"{$perangkat->nama}\" dinonaktifkan. Teller tidak bisa lagi memakai komputer ini.");
    }

    public function destroy(Perangkat $perangkat): RedirectResponse
    {
        $perangkat->delete();

        return back()->with('success', "\"{$perangkat->nama}\" dihapus dari daftar.");
    }
}
