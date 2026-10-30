<?php

use App\Http\Modules\Kas\Models\SesiKas;
use App\Http\Modules\Kas\Services\SesiKasService;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Test di tests/Feature dan app/Http/Modules/{Modul}/Tests memakai TestCase
| Laravel dengan database yang di-refresh setiap test.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', '../app/Http/Modules/*/Tests');

/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

/**
 * User aktif dengan permission tertentu (permission dibuat bila belum ada).
 */
function userWithPermissions(string ...$permissions): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    return User::factory()->create()->givePermissionTo($permissions);
}

/**
 * User aktif dengan peran tertentu, setelah role & permission disinkronkan.
 */
function userWithRole(string $role): User
{
    if (Permission::count() === 0) {
        (new RolePermissionSeeder)->run();
    }

    return User::factory()->create()->assignRole($role);
}

/**
 * Membuka sesi kas tanggal transaksi aktif agar teller bisa bertransaksi.
 */
function bukaSesiKas(int|string $modal = 0): SesiKas
{
    return app(SesiKasService::class)->buka($modal);
}
