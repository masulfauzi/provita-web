<?php

namespace App\Providers;

use App\Support\Modules\Module;
use App\Support\Modules\ModuleRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

/**
 * Memuat route, migration, dan policy setiap modul di app/Http/Modules
 * tanpa perlu didaftarkan manual. Menghapus folder modul berarti
 * route/menu/migration-nya ikut hilang.
 */
class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModuleRegistry::class, fn () => new ModuleRegistry(app_path('Http/Modules')));
    }

    public function boot(ModuleRegistry $registry): void
    {
        $this->registerPolicyGuesser();

        foreach ($registry->all() as $module) {
            foreach ($module->policies as $model => $policy) {
                Gate::policy($model, $policy);
            }

            if (is_dir($module->path('Database/Migrations'))) {
                $this->loadMigrationsFrom($module->path('Database/Migrations'));
            }

            if (! $this->app->routesAreCached()) {
                $this->registerRoutes($module);
            }

            if ($this->app->runningInConsole()) {
                $this->registerCommands($module);
            }
        }
    }

    /**
     * Artisan command modul: app/Http/Modules/{Modul}/Console/*.php
     */
    protected function registerCommands(Module $module): void
    {
        $commands = collect(glob($module->path('Console/*.php')) ?: [])
            ->map(fn (string $file) => $module->namespace('Console\\'.basename($file, '.php')))
            ->filter(fn (string $class) => is_subclass_of($class, Command::class))
            ->all();

        $this->commands($commands);
    }

    protected function registerRoutes(Module $module): void
    {
        if (is_file($module->path('routes/web.php'))) {
            Route::middleware(['web', 'auth'])->group($module->path('routes/web.php'));
        }

        if (is_file($module->path('routes/api.php'))) {
            Route::prefix('api/v1')
                ->name('api.')
                ->middleware(['api', 'auth:sanctum'])
                ->group($module->path('routes/api.php'));
        }
    }

    /**
     * App\Http\Modules\X\Models\Y => App\Http\Modules\X\Policies\YPolicy,
     * model di luar modul tetap memakai konvensi App\Policies\YPolicy.
     */
    protected function registerPolicyGuesser(): void
    {
        Gate::guessPolicyNamesUsing(function (string $class) {
            if (Str::startsWith($class, 'App\\Http\\Modules\\') && Str::contains($class, '\\Models\\')) {
                return [Str::replace('\\Models\\', '\\Policies\\', $class).'Policy'];
            }

            return ['App\\Policies\\'.class_basename($class).'Policy'];
        });
    }
}
