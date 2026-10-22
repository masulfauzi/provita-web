<?php

namespace App\Http\Middleware;

use App\Http\Modules\Pengaturan\Services\PengaturanService;
use App\Support\Modules\ModuleRegistry;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user?->only('id', 'username', 'name', 'email'),
                'roles' => fn () => $user?->getRoleNames() ?? [],
                'permissions' => fn () => $user?->getAllPermissions()->pluck('name') ?? [],
            ],
            'menu' => fn () => app(ModuleRegistry::class)->menuFor($user),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'app' => [
                'name' => config('app.name'),
                'tanggal_transaksi' => fn () => $this->tanggalTransaksi(),
            ],
        ];
    }

    protected function tanggalTransaksi(): string
    {
        if (class_exists(PengaturanService::class)) {
            return app(PengaturanService::class)->tanggalTransaksi()->toDateString();
        }

        return now()->toDateString();
    }
}
