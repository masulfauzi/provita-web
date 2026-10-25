<?php

namespace App\Http\Modules\Kas\Policies;

use App\Http\Modules\Kas\Models\Kas;
use App\Models\User;

class KasPolicy
{
    public function view(User $user, Kas $kas): bool
    {
        return $user->can('kas.lihat');
    }

    public function create(User $user, string $jenis): bool
    {
        return $user->can($jenis === Kas::MASUK ? 'kas.masuk' : 'kas.keluar');
    }

    public function cancel(User $user, Kas $kas): bool
    {
        return $user->can('kas.batal') && ! $kas->dibatalkan();
    }
}
