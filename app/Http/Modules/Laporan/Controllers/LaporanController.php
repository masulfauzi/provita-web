<?php

namespace App\Http\Modules\Laporan\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kerangka modul Laporan; laporan sebenarnya dibuat di issue modul Laporan.
 */
class LaporanController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('SegeraHadir', [
            'judul' => 'Laporan keuangan',
            'keterangan' => 'Neraca, laba rugi, dan laporan arus kas akan tersedia di sini.',
        ]);
    }

    public function neraca(): Response
    {
        return Inertia::render('SegeraHadir', [
            'judul' => 'Neraca',
            'keterangan' => 'Laporan neraca akan tersedia di sini.',
        ]);
    }
}
