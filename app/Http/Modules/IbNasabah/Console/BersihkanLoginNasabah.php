<?php

namespace App\Http\Modules\IbNasabah\Console;

use App\Http\Modules\IbNasabah\Services\AkunNasabahService;
use Illuminate\Console\Command;

class BersihkanLoginNasabah extends Command
{
    protected $signature = 'ib:bersihkan-login';

    protected $description = 'Menghapus riwayat login Internet Banking lama (menyimpan 50 entri terakhir per akun)';

    public function handle(AkunNasabahService $service): int
    {
        $this->components->info($service->bersihkanRiwayatLogin().' riwayat login lama dihapus.');

        return self::SUCCESS;
    }
}
