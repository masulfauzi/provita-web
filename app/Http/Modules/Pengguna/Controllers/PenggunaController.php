<?php

namespace App\Http\Modules\Pengguna\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Modules\Pengguna\Requests\StorePenggunaRequest;
use App\Http\Modules\Pengguna\Requests\UpdatePenggunaRequest;
use App\Http\Modules\Pengguna\Services\PenggunaService;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class PenggunaController extends Controller
{
    public function __construct(protected PenggunaService $service) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        return Inertia::render('Pengguna::Index', [
            'items' => $this->service->paginate(
                search: $request->string('search')->toString(),
                role: $request->string('role')->toString() ?: null,
                perPage: $request->integer('per_page', 15),
            ),
            'filters' => $request->only('search', 'role'),
            'roles' => $this->roleOptions(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', User::class);

        return Inertia::render('Pengguna::Create', ['roles' => $this->roleOptions()]);
    }

    public function store(StorePenggunaRequest $request): RedirectResponse
    {
        $this->service->create($request->validated());

        return redirect()->route('pengguna.index')->with('success', 'Pengguna berhasil ditambahkan.');
    }

    public function edit(User $pengguna): Response
    {
        Gate::authorize('update', $pengguna);

        return Inertia::render('Pengguna::Edit', [
            'item' => [
                ...$pengguna->only('id', 'username', 'name', 'email', 'is_active'),
                'roles' => $pengguna->getRoleNames(),
                'is_self' => $pengguna->is(request()->user()),
            ],
            'roles' => $this->roleOptions(),
        ]);
    }

    public function update(UpdatePenggunaRequest $request, User $pengguna): RedirectResponse
    {
        $this->service->update($pengguna, $request->validated());

        return redirect()->route('pengguna.index')->with('success', 'Pengguna berhasil diperbarui.');
    }

    public function resetPassword(Request $request, User $pengguna): RedirectResponse
    {
        Gate::authorize('resetPassword', $pengguna);

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ], attributes: ['password' => 'kata sandi baru']);

        $this->service->resetPassword($pengguna, $validated['password']);

        return back()->with('success', "Kata sandi {$pengguna->username} berhasil direset.");
    }

    public function toggleActive(User $pengguna): RedirectResponse
    {
        Gate::authorize('toggleActive', $pengguna);

        $this->service->setActive($pengguna, ! $pengguna->is_active);

        return back()->with('success', $pengguna->is_active
            ? "Pengguna {$pengguna->username} diaktifkan."
            : "Pengguna {$pengguna->username} dinonaktifkan.");
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    protected function roleOptions(): array
    {
        return collect(config('permissions.roles'))
            ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }
}
