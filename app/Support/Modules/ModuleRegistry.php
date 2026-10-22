<?php

namespace App\Support\Modules;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * Memindai app/Http/Modules/* dan menyediakan daftar modul, menu sidebar,
 * serta daftar permission yang dideklarasikan di module.json.
 */
class ModuleRegistry
{
    /** @var Collection<int, Module>|null */
    protected ?Collection $modules = null;

    public function __construct(protected string $basePath) {}

    /**
     * @return Collection<int, Module>
     */
    public function all(): Collection
    {
        return $this->modules ??= collect(glob($this->basePath.'/*', GLOB_ONLYDIR) ?: [])
            ->map(fn (string $path) => Module::fromPath($path))
            ->sortBy([['order', 'asc'], ['name', 'asc']])
            ->values();
    }

    public function find(string $name): ?Module
    {
        return $this->all()->first(fn (Module $module) => $module->name === $name);
    }

    /**
     * Semua permission: dari module.json tiap modul ditambah config/permissions.php
     * (permission untuk modul yang belum dibuat).
     *
     * @return array<string, array{label: string, roles: list<string>}>
     */
    public function permissions(): array
    {
        $permissions = [];

        $sources = $this->all()->map(fn (Module $module) => $module->permissions)
            ->push(config('permissions.permissions', []));

        foreach ($sources as $source) {
            foreach ($source as $name => $definition) {
                $permissions[$name] = [
                    'label' => $definition['label'] ?? $name,
                    'roles' => array_values(array_unique([
                        ...($permissions[$name]['roles'] ?? []),
                        ...($definition['roles'] ?? []),
                    ])),
                ];
            }
        }

        ksort($permissions);

        return $permissions;
    }

    /**
     * Menu sidebar yang boleh dilihat user, dikelompokkan per grup.
     *
     * @return list<array{label: string, items: list<array{label: string, icon: string, url: string, route: string}>}>
     */
    public function menuFor(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $items = $this->all()->flatMap(fn (Module $module) => collect($module->menu)->map(fn (array $item) => [
            'label' => $item['label'] ?? $module->label,
            'icon' => $item['icon'] ?? $module->icon,
            'route' => $item['route'],
            'permission' => $item['permission'] ?? null,
            'group' => $item['group'] ?? 'Menu',
            'order' => $item['order'] ?? $module->order,
        ]))
            ->filter(fn (array $item) => Route::has($item['route']))
            ->filter(fn (array $item) => $item['permission'] === null || $user->can($item['permission']))
            ->sortBy('order');

        return $items->groupBy('group')
            ->map(fn (Collection $items, string $group) => [
                'label' => $group,
                'items' => $items->map(fn (array $item) => [
                    'label' => $item['label'],
                    'icon' => $item['icon'],
                    'route' => $item['route'],
                    'url' => route($item['route'], absolute: false),
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }
}
