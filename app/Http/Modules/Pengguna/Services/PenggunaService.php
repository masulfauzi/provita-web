<?php

namespace App\Http\Modules\Pengguna\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PenggunaService
{
    public function paginate(string $search = '', ?string $role = null, int $perPage = 15): LengthAwarePaginator
    {
        return User::query()
            ->with('roles:id,name')
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('username', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")))
            ->when($role, fn ($query) => $query->role($role))
            ->orderBy('name')
            ->paginate(min(max($perPage, 5), 100))
            ->withQueryString()
            ->through(fn (User $user) => [
                ...$user->only('id', 'username', 'name', 'email', 'is_active'),
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'roles' => $user->roles->pluck('name'),
            ]);
    }

    /**
     * @param  array{username: string, name: string, email?: string|null, password: string, roles: list<string>}  $data
     */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'username' => $data['username'],
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'password' => $data['password'],
            ]);

            $user->syncRoles($data['roles']);

            return $user;
        });
    }

    /**
     * @param  array{name: string, email?: string|null, roles: list<string>}  $data
     */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $user->update([
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
            ]);

            $before = $user->getRoleNames()->sort()->values()->all();
            $user->syncRoles($data['roles']);
            $after = collect($data['roles'])->sort()->values()->all();

            if ($before !== $after) {
                activity('pengguna')->performedOn($user)->event('roles_changed')
                    ->withProperties(['old' => $before, 'attributes' => $after])
                    ->log('Mengubah peran pengguna');
            }

            return $user;
        });
    }

    public function resetPassword(User $user, string $password): void
    {
        $user->update(['password' => $password]);

        // Paksa keluar dari semua sesi & token lama.
        DB::table('sessions')->where('user_id', $user->id)->delete();
        $user->tokens()->delete();

        activity('pengguna')->performedOn($user)->event('password_reset')->log('Reset kata sandi pengguna');
    }

    public function setActive(User $user, bool $active): void
    {
        $user->update(['is_active' => $active]);

        if (! $active) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->tokens()->delete();
        }
    }
}
