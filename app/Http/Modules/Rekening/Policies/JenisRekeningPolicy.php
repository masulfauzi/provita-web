<?php

namespace App\Http\Modules\Rekening\Policies;

use App\Http\Modules\Rekening\Models\JenisRekening;
use App\Models\User;

class JenisRekeningPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('jenis_rekening.lihat');
    }

    public function create(User $user): bool
    {
        return $user->can('jenis_rekening.tambah');
    }

    public function update(User $user, JenisRekening $jenis): bool
    {
        return $user->can('jenis_rekening.ubah');
    }

    public function delete(User $user, JenisRekening $jenis): bool
    {
        return $user->can('jenis_rekening.hapus');
    }
}
