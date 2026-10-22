<?php

namespace App\Support\Modules;

/**
 * Representasi satu modul di app/Http/Modules beserta isi module.json-nya.
 */
final readonly class Module
{
    /**
     * @param  list<array{label: string, route: string, icon?: string, permission?: string, group?: string, order?: int}>  $menu
     * @param  array<string, array{label?: string, roles?: list<string>}>  $permissions
     * @param  array<class-string, class-string>  $policies  model di luar modul => policy di modul
     */
    public function __construct(
        public string $name,
        public string $path,
        public string $label,
        public string $icon,
        public int $order,
        public array $menu,
        public array $permissions,
        public array $policies = [],
    ) {}

    public static function fromPath(string $path): self
    {
        $name = basename($path);
        $manifest = [];

        if (is_file($path.'/module.json')) {
            $manifest = json_decode(file_get_contents($path.'/module.json'), true, flags: JSON_THROW_ON_ERROR);
        }

        return new self(
            name: $name,
            path: $path,
            label: $manifest['label'] ?? $name,
            icon: $manifest['icon'] ?? 'pi pi-box',
            order: (int) ($manifest['order'] ?? 100),
            menu: $manifest['menu'] ?? [],
            permissions: $manifest['permissions'] ?? [],
            policies: $manifest['policies'] ?? [],
        );
    }

    public function namespace(string $suffix = ''): string
    {
        return 'App\\Http\\Modules\\'.$this->name.($suffix !== '' ? '\\'.$suffix : '');
    }

    public function path(string $relative = ''): string
    {
        return $this->path.($relative !== '' ? '/'.$relative : '');
    }
}
