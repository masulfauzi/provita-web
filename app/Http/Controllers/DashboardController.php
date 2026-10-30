<?php

namespace App\Http\Controllers;

use App\Http\Modules\Kas\Services\SesiKasService;
use App\Http\Modules\Nasabah\Models\Nasabah;
use App\Http\Modules\Pengaturan\Services\PengaturanService;
use App\Http\Modules\Transaksi\Services\RingkasanService;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $tanggal = class_exists(PengaturanService::class)
            ? app(PengaturanService::class)->tanggalTransaksi()
            : today();

        return Inertia::render('Dashboard', [
            'tanggal' => $tanggal->toDateString(),
            'ringkasan' => $this->ringkasan($tanggal),
        ]);
    }

    /**
     * Kartu ringkasan untuk tanggal transaksi aktif. Modul dicek dengan
     * class_exists() agar dashboard tetap jalan bila sebuah modul dihapus.
     *
     * @return list<array{label: string, nilai: string, tipe: string, icon: string, keterangan: string|null, url: string|null}>
     */
    protected function ringkasan(Carbon $tanggal): array
    {
        $kartu = [];

        if (class_exists(RingkasanService::class)) {
            $harian = app(RingkasanService::class)->harian($tanggal);
            $sesi = class_exists(SesiKasService::class) ? app(SesiKasService::class)->hariIni() : null;
            $modal = $sesi ? Money::of($sesi->modal_awal) : '0.00';

            $kartu[] = $this->kartu('Saldo kas hari ini', Money::add($modal, $harian['mutasi_kas']), 'uang', 'pi pi-wallet',
                ($sesi ? 'Modal sesi '.Money::format($modal) : 'Sesi kas belum dibuka').' + mutasi kas '.Money::format($harian['mutasi_kas'])
                .(Money::compare($harian['kas_lain'], 0) !== 0 ? ' (termasuk kas lain-lain '.Money::format($harian['kas_lain']).')' : ''),
                '/kas/sesi');
            $kartu[] = $this->kartu('Setoran hari ini', $harian['setoran'], 'uang', 'pi pi-arrow-down',
                $harian['jumlah_setoran'].' transaksi', '/rekening');
            $kartu[] = $this->kartu('Penarikan hari ini', $harian['penarikan'], 'uang', 'pi pi-arrow-up',
                $harian['jumlah_penarikan'].' transaksi', '/rekening');
            $kartu[] = $this->kartu('Total saldo tabungan', $harian['total_saldo'], 'uang', 'pi pi-book',
                $harian['jumlah_rekening'].' rekening aktif', '/rekening');
        }

        if (class_exists(Nasabah::class)) {
            $kartu[] = $this->kartu('Nasabah aktif', (string) Nasabah::query()->where('is_active', true)->count(), 'angka',
                'pi pi-users', null, '/nasabah');
        }

        return $kartu;
    }

    /**
     * @return array{label: string, nilai: string, tipe: string, icon: string, keterangan: string|null, url: string|null}
     */
    protected function kartu(string $label, string $nilai, string $tipe, string $icon, ?string $keterangan = null, ?string $url = null): array
    {
        return compact('label', 'nilai', 'tipe', 'icon', 'keterangan', 'url');
    }
}
