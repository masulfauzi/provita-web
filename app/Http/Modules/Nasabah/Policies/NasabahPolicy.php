<?php

namespace App\Http\Modules\Nasabah\Policies;

use App\Http\Modules\Nasabah\Models\Nasabah;
use App\Models\User;

class NasabahPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('nasabah.lihat');
    }

    public function view(User $user, Nasabah $nasabah): bool
    {
        return $user->can('nasabah.lihat');
    }

    public function create(User $user): bool
    {
        return $user->can('nasabah.tambah');
    }

    public function update(User $user, Nasabah $nasabah): bool
    {
        return $user->can('nasabah.ubah');
    }

    public function toggleActive(User $user, Nasabah $nasabah): bool
    {
        return $user->can('nasabah.hapus');
    }

    public function delete(User $user, Nasabah $nasabah): bool
    {
        return $user->can('nasabah.hapus');
    }
}
