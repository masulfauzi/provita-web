<?php

namespace App\Http\Modules\Pinjaman\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kerangka modul Pinjaman; fitur akad & angsuran dibuat di issue modul Pinjaman.
 */
class PinjamanController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('SegeraHadir', [
            'judul' => 'Pinjaman',
            'keterangan' => 'Akad pinjaman, jadwal, dan angsuran akan tersedia di sini.',
        ]);
    }
}
