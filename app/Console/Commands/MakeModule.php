<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Symfony\Component\Finder\SplFileInfo;

class MakeModule extends Command
{
    protected $signature = 'make:module
        {name : Nama modul dalam StudlyCase, mis. Nasabah}
        {--menu : Tampilkan modul di menu sidebar}
        {--api : Buat juga route & controller API (api/v1)}
        {--group=Data Master : Grup menu sidebar}
        {--force : Timpa modul yang sudah ada}';

    protected $description = 'Membuat modul baru di app/Http/Modules lengkap dengan CRUD Inertia + PrimeVue';

    public function handle(Filesystem $files): int
    {
        $name = Str::studly($this->argument('name'));
        $target = app_path('Http/Modules/'.$name);

        if ($files->isDirectory($target) && ! $this->option('force')) {
            $this->components->error("Modul [{$name}] sudah ada.");

            return self::FAILURE;
        }

        $replacements = $this->replacements($name);
        $stubDirs = [base_path('stubs/module')];

        if ($this->option('api')) {
            $stubDirs[] = base_path('stubs/module-api');
        }

        foreach ($stubDirs as $stubDir) {
            /** @var SplFileInfo $stub */
            foreach ($files->allFiles($stubDir) as $stub) {
                $relative = Str::replaceLast('.stub', '', strtr($stub->getRelativePathname(), $replacements));
                $path = $target.'/'.$relative;

                $files->ensureDirectoryExists(dirname($path));
                $files->put($path, strtr($stub->getContents(), $replacements));
            }
        }

        $this->components->info("Modul [{$name}] dibuat di app/Http/Modules/{$name}.");

        try {
            $this->callSilently('permission:sync-modules');
            $synced = 'Permission modul sudah disinkronkan (peran bendahara otomatis mendapat semua permission).';
        } catch (QueryException) {
            $synced = 'Database belum siap; jalankan <comment>php artisan permission:sync-modules</comment> nanti.';
        }

        $this->components->bulletList([
            'Jalankan <comment>php artisan migrate</comment> untuk membuat tabel '.$replacements['{{table}}'].'.',
            $synced,
            "Halaman: <comment>/{$replacements['{{module}}']}</comment>",
        ]);

        return self::SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    protected function replacements(string $name): array
    {
        $menu = $this->option('menu')
            ? json_encode([[
                'label' => Str::headline($name),
                'route' => Str::kebab($name).'.index',
                'permission' => Str::snake($name).'.lihat',
                'group' => $this->option('group'),
            ]], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : '[]';

        return [
            '{{Module}}' => $name,
            '{{module}}' => Str::kebab($name),
            '{{permission}}' => Str::snake($name),
            '{{table}}' => Str::snake($name),
            '{{variable}}' => Str::camel($name),
            '{{label}}' => Str::headline($name),
            '{{timestamp}}' => now()->format('Y_m_d_His'),
            '{{menu}}' => $menu,
        ];
    }
}
