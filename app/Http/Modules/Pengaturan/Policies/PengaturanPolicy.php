<?php

namespace App\Http\Modules\Pengaturan\Policies;

use App\Models\User;

class PengaturanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('pengaturan.lihat');
    }
}
