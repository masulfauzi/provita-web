<?php

namespace Database\Seeders;

use App\Support\Modules\ModuleRegistry;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);
        $this->call(UserSeeder::class);

        // Seeder setiap modul: app/Http/Modules/{Modul}/Database/Seeders/{Modul}Seeder.php
        foreach (app(ModuleRegistry::class)->all() as $module) {
            $seeder = $module->namespace('Database\\Seeders\\'.$module->name.'Seeder');

            if (class_exists($seeder)) {
                $this->call($seeder);
            }
        }
    }
}
