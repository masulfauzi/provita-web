<?php

namespace App\Http\Modules\Akuntansi\Policies;

use App\Http\Modules\Akuntansi\Models\Jurnal;
use App\Models\User;

class JurnalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('jurnal.lihat');
    }

    public function view(User $user, Jurnal $jurnal): bool
    {
        return $user->can('jurnal.lihat');
    }

    public function create(User $user): bool
    {
        return $user->can('jurnal.tambah');
    }

    public function reverse(User $user, Jurnal $jurnal): bool
    {
        return $user->can('jurnal.balik');
    }
}
