<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        // Ringkasan masih statis; diisi dari modul Kas/Rekening di issue berikutnya.
        return Inertia::render('Dashboard', [
            'ringkasan' => [
                ['label' => 'Saldo kas hari ini', 'nilai' => '0', 'tipe' => 'uang', 'icon' => 'pi pi-wallet'],
                ['label' => 'Setoran hari ini', 'nilai' => '0', 'tipe' => 'uang', 'icon' => 'pi pi-arrow-down'],
                ['label' => 'Penarikan hari ini', 'nilai' => '0', 'tipe' => 'uang', 'icon' => 'pi pi-arrow-up'],
                ['label' => 'Nasabah aktif', 'nilai' => '0', 'tipe' => 'angka', 'icon' => 'pi pi-users'],
            ],
        ]);
    }
}
