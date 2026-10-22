<?php

namespace App\Http\Modules\Rekening\Policies;

use App\Http\Modules\Rekening\Models\Rekening;
use App\Models\User;

class RekeningPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('rekening.lihat');
    }

    public function view(User $user, Rekening $rekening): bool
    {
        return $user->can('rekening.lihat');
    }

    public function create(User $user): bool
    {
        return $user->can('rekening.buka');
    }

    public function close(User $user, Rekening $rekening): bool
    {
        return $user->can('rekening.tutup') && $rekening->isAktif();
    }
}
