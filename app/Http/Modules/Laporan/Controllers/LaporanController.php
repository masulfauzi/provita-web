<?php

namespace App\Http\Modules\Laporan\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Modules\Akuntansi\Models\Akun;
use App\Http\Modules\Kas\Services\SesiKasService;
use App\Http\Modules\Laporan\Exports\LaporanExport;
use App\Http\Modules\Laporan\Services\LaporanService;
use App\Http\Modules\Laporan\Services\TabelLaporan;
use App\Http\Modules\Pengaturan\Services\PengaturanService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LaporanController extends Controller
{
    /**
     * slug => [judul, per tanggal (true) atau per periode (false)].
     */
    public const LAPORAN = [
        'neraca' => ['Neraca', true],
        'laba-rugi' => ['Laporan laba rugi', false],
        'arus-kas' => ['Laporan arus kas', false],
        'neraca-saldo' => ['Neraca saldo', true],
        'buku-besar' => ['Buku besar', false],
    ];

    public function __construct(
        protected LaporanService $service,
        protected PengaturanService $pengaturan,
    ) {}

    public function index(Request $request): Response
    {
        [$dari, $sampai] = $this->periode($request);
        $neraca = $this->service->neraca($sampai);
        $arusKas = $this->service->arusKas($dari, $sampai);

        return Inertia::render('Laporan::Index', [
            'periode' => compact('dari', 'sampai'),
            'daftar' => $this->daftar(),
            'ringkasan' => [
                'kas' => $arusKas['saldo_akhir'],
                'total_aset' => $neraca['total_aset'],
                'simpanan' => $neraca['total_kewajiban'],
                'laba' => $this->service->labaRugi($dari, $sampai)['laba'],
                'seimbang' => $neraca['seimbang'],
            ],
        ]);
    }

    public function show(Request $request, string $laporan): Response
    {
        [$dari, $sampai] = $this->periode($request);
        $akun = $laporan === 'buku-besar' ? $this->akun($request) : null;

        return Inertia::render('Laporan::Laporan', [
            'laporan' => $laporan,
            'judul' => self::LAPORAN[$laporan][0],
            'perTanggal' => self::LAPORAN[$laporan][1],
            'periode' => compact('dari', 'sampai'),
            'data' => $this->data($laporan, $dari, $sampai, $akun),
            'kop' => $this->kop($laporan, $dari, $sampai),
            'daftar' => $this->daftar(),
            'akunOptions' => $laporan === 'buku-besar'
                ? Akun::query()->orderBy('kode')->get(['id', 'kode', 'nama'])
                    ->map(fn (Akun $item) => ['value' => $item->id, 'label' => "{$item->kode} · {$item->nama}"])
                : [],
        ]);
    }

    public function excel(Request $request, string $laporan): BinaryFileResponse
    {
        [$dari, $sampai] = $this->periode($request);
        $akun = $laporan === 'buku-besar' ? $this->akun($request) : null;
        $kop = $this->kop($laporan, $dari, $sampai);

        $tabel = TabelLaporan::buat($laporan, $this->data($laporan, $dari, $sampai, $akun), $kop);
        $nama = str_replace('-', '_', $laporan).'_'.($kop['per_tanggal'] ? $sampai : "{$dari}_{$sampai}").'.xlsx';

        return Excel::download(new LaporanExport($kop['judul'], $tabel), $nama);
    }

    /**
     * Laporan kas harian: modal awal sesi + pemasukan - pengeluaran uang fisik pada satu tanggal.
     */
    public function harian(Request $request): Response
    {
        $tanggal = $this->tanggalHarian($request);

        return Inertia::render('Laporan::Harian', [
            'tanggal' => $tanggal,
            'data' => app(SesiKasService::class)->rekap($tanggal),
            'kop' => $this->kopHarian($tanggal),
        ]);
    }

    public function harianExcel(Request $request): BinaryFileResponse
    {
        $tanggal = $this->tanggalHarian($request);
        $kop = $this->kopHarian($tanggal);
        $tabel = TabelLaporan::buat('harian', app(SesiKasService::class)->rekap($tanggal), $kop);

        return Excel::download(new LaporanExport($kop['judul'], $tabel), "kas_harian_{$tanggal}.xlsx");
    }

    /**
     * @return array<string, mixed>
     */
    protected function data(string $laporan, string $dari, string $sampai, ?Akun $akun): array
    {
        return match ($laporan) {
            'neraca' => $this->service->neraca($sampai),
            'laba-rugi' => $this->service->labaRugi($dari, $sampai),
            'arus-kas' => $this->service->arusKas($dari, $sampai),
            'neraca-saldo' => $this->service->neracaSaldo($sampai),
            'buku-besar' => $this->service->bukuBesar($akun, $dari, $sampai),
        };
    }

    /**
     * Periode dari query ?dari=&sampai=; default awal bulan s.d. tanggal transaksi aktif.
     *
     * @return array{0: string, 1: string}
     */
    protected function periode(Request $request): array
    {
        $request->validate([
            'dari' => ['nullable', 'date_format:Y-m-d'],
            'sampai' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $sampai = $request->filled('sampai') ? Carbon::parse($request->string('sampai')) : $this->pengaturan->tanggalTransaksi();
        $dari = $request->filled('dari') ? Carbon::parse($request->string('dari')) : $sampai->copy()->startOfMonth();

        if ($dari->greaterThan($sampai)) {
            [$dari, $sampai] = [$sampai, $dari];
        }

        return [$dari->toDateString(), $sampai->toDateString()];
    }

    protected function tanggalHarian(Request $request): string
    {
        $request->validate(['tanggal' => ['nullable', 'date_format:Y-m-d']]);

        return $request->filled('tanggal')
            ? $request->string('tanggal')->toString()
            : $this->pengaturan->tanggalTransaksi()->toDateString();
    }

    protected function akun(Request $request): Akun
    {
        return $request->filled('akun')
            ? Akun::query()->findOrFail($request->string('akun'))
            : Akun::peran(Akun::KAS);
    }

    /**
     * Kop laporan untuk cetak & Excel.
     *
     * @return array<string, mixed>
     */
    protected function kop(string $laporan, string $dari, string $sampai): array
    {
        [$judul, $perTanggal] = self::LAPORAN[$laporan];
        $tanggal = fn (string $nilai) => Carbon::parse($nilai)->translatedFormat('j F Y');

        return $this->kopDasar(
            $judul,
            $perTanggal ? 'Per '.$tanggal($sampai) : 'Periode '.$tanggal($dari).' s.d. '.$tanggal($sampai),
            $tanggal($sampai),
        ) + ['per_tanggal' => $perTanggal];
    }

    /**
     * @return array<string, mixed>
     */
    protected function kopHarian(string $tanggal): array
    {
        $tanggal = Carbon::parse($tanggal);

        return $this->kopDasar('Laporan kas harian', 'Tanggal '.$tanggal->translatedFormat('l, j F Y'), $tanggal->translatedFormat('j F Y'))
            + ['per_tanggal' => true];
    }

    /**
     * @return array<string, mixed>
     */
    protected function kopDasar(string $judul, string $periode, string $tanggalCetak): array
    {
        return [
            'judul' => $judul,
            'periode' => $periode,
            'nama_bank' => $this->pengaturan->get('nama_bank') ?: config('app.name'),
            'nama_sekolah' => $this->pengaturan->get('nama_sekolah'),
            'alamat_sekolah' => $this->pengaturan->get('alamat_sekolah'),
            'nama_kepala_sekolah' => $this->pengaturan->get('nama_kepala_sekolah'),
            'tanggal_cetak' => $tanggalCetak,
        ];
    }

    /**
     * @return list<array{slug: string, judul: string}>
     */
    protected function daftar(): array
    {
        return collect(self::LAPORAN)
            ->map(fn (array $item, string $slug) => ['slug' => $slug, 'judul' => $item[0]])
            ->values()
            ->all();
    }
}
