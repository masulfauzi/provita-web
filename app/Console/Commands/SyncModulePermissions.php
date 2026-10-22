<?php

namespace App\Console\Commands;

use App\Support\Modules\ModuleRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SyncModulePermissions extends Command
{
    protected $signature = 'permission:sync-modules {--prune : Hapus permission yang tidak lagi dideklarasikan}';

    protected $description = 'Membuat role & permission dari config/permissions.php dan module.json setiap modul';

    public function handle(): int
    {
        if (! Schema::hasTable(config('permission.table_names.permissions'))) {
            $this->components->warn('Tabel permission belum ada, jalankan migrate terlebih dahulu.');

            return self::SUCCESS;
        }

        // Registry dibuat ulang supaya modul yang baru di-generate ikut terbaca.
        $registry = new ModuleRegistry(app_path('Http/Modules'));
        $permissions = $registry->permissions();
        $guard = config('auth.defaults.guard');

        $roles = collect(config('permissions.roles'))
            ->keys()
            ->mapWithKeys(fn (string $role) => [$role => Role::findOrCreate($role, $guard)]);

        foreach ($permissions as $name => $definition) {
            $permission = Permission::findOrCreate($name, $guard);

            foreach ($definition['roles'] as $roleName) {
                ($roles[$roleName] ??= Role::findOrCreate($roleName, $guard))->givePermissionTo($permission);
            }
        }

        foreach (config('permissions.all_permissions_roles', []) as $roleName) {
            ($roles[$roleName] ??= Role::findOrCreate($roleName, $guard))->givePermissionTo(array_keys($permissions));
        }

        if ($this->option('prune')) {
            $deleted = Permission::query()->whereNotIn('name', array_keys($permissions))->delete();
            $this->components->info("{$deleted} permission usang dihapus.");
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->components->info(count($permissions).' permission & '.$roles->count().' peran disinkronkan.');

        return self::SUCCESS;
    }
}
