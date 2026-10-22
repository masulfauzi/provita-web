<?php

namespace App\Http\Modules\Pengguna\Policies;

use App\Models\User;

class PenggunaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('pengguna.lihat');
    }

    public function create(User $user): bool
    {
        return $user->can('pengguna.tambah');
    }

    public function update(User $user, User $target): bool
    {
        return $user->can('pengguna.ubah');
    }

    public function resetPassword(User $user, User $target): bool
    {
        return $user->can('pengguna.reset_password');
    }

    /**
     * Tidak boleh menonaktifkan akun sendiri agar tidak terkunci dari aplikasi.
     */
    public function toggleActive(User $user, User $target): bool
    {
        return $user->can('pengguna.ubah') && ! $user->is($target);
    }
}
