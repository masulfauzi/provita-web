<?php

namespace App\Http\Modules\Akuntansi\Policies;

use App\Http\Modules\Akuntansi\Models\Akun;
use App\Models\User;

class AkunPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('akun.lihat');
    }

    public function create(User $user): bool
    {
        return $user->can('akun.kelola');
    }

    public function update(User $user, Akun $akun): bool
    {
        return $user->can('akun.kelola');
    }

    public function delete(User $user, Akun $akun): bool
    {
        return $user->can('akun.kelola');
    }
}
