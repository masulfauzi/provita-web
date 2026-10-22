<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Membuat akun awal bendahara & teller. Username dan password dibaca dari
 * .env (SEED_*), tidak pernah ditulis di kode.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('seed.users') as $role => $account) {
            if (blank($account['username'])) {
                continue;
            }

            if (blank($account['password'])) {
                throw new RuntimeException("Password awal untuk peran {$role} belum diisi di .env (SEED_".strtoupper($role).'_PASSWORD).');
            }

            $user = User::firstOrCreate(
                ['username' => $account['username']],
                ['name' => $account['name'], 'password' => $account['password']],
            );

            $user->syncRoles([$role]);
        }
    }
}
