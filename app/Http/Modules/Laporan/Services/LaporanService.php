<?php

namespace App\Http\Modules\Laporan\Services;

use App\Http\Modules\Akuntansi\Models\Akun;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Laporan keuangan yang dihitung dari jurnal (akun & jurnal_detail).
 * Semua nominal berupa string desimal (bcmath), tidak pernah float.
 */
class LaporanService
{
    /**
     * Peran akun yang dihitung sebagai kas di laporan arus kas.
     */
    public const AKUN_KAS = [Akun::KAS, Akun::BANK];

    /**
     * Neraca per tanggal: aset = kewajiban + ekuitas. Laba berjalan (pendapatan − beban)
     * masuk ekuitas karena belum ada proses tutup buku.
     *
     * @return array<string, mixed>
     */
    public function neraca(string $tanggal): array
    {
        $akun = $this->saldoAkun(null, $tanggal);
        $kelompok = fn (string $kategori) => $this->tampilkan($akun->where('kategori', $kategori));

        $aset = $kelompok('aset');
        $kewajiban = $kelompok('kewajiban');
        $ekuitas = $kelompok('ekuitas');
        $laba = $this->laba($akun);

        $totalAset = $this->total($aset);
        $totalKewajiban = $this->total($kewajiban);
        $totalEkuitas = Money::add($this->total($ekuitas), $laba);
        $totalPasiva = Money::add($totalKewajiban, $totalEkuitas);

        return [
            'aset' => $aset,
            'kewajiban' => $kewajiban,
            'ekuitas' => $ekuitas,
            'laba_berjalan' => $laba,
            'total_aset' => $totalAset,
            'total_kewajiban' => $totalKewajiban,
            'total_ekuitas' => $totalEkuitas,
            'total_pasiva' => $totalPasiva,
            'seimbang' => Money::compare($totalAset, $totalPasiva) === 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function labaRugi(string $dari, string $sampai): array
    {
        $akun = $this->saldoAkun($dari, $sampai);
        $pendapatan = $this->tampilkan($akun->where('kategori', 'pendapatan'));
        $beban = $this->tampilkan($akun->where('kategori', 'beban'));

        return [
            'pendapatan' => $pendapatan,
            'beban' => $beban,
            'total_pendapatan' => $this->total($pendapatan),
            'total_beban' => $this->total($beban),
            'laba' => $this->laba($akun),
        ];
    }

    /**
     * Arus kas metode langsung. Setiap baris jurnal non-kas pada jurnal yang menyentuh kas
     * adalah lawan arus kasnya: kredit = kas masuk, debit = kas keluar. Pemindahan antar
     * akun kas (kas ↔ bank) tidak memiliki baris lawan sehingga tidak dihitung.
     *
     * @return array<string, mixed>
     */
    public function arusKas(string $dari, string $sampai): array
    {
        $kas = Akun::query()->whereIn('peran', self::AKUN_KAS)->pluck('id');

        $saldoAwal = $this->saldoKas($kas, $this->sebelum($dari));

        $lawan = DB::table('jurnal_detail as d')
            ->join('jurnal as j', 'j.id', '=', 'd.jurnal_id')
            ->join('akun as a', 'a.id', '=', 'd.akun_id')
            ->whereBetween('j.tanggal', [$dari, $this->akhir($sampai)])
            ->whereNotIn('d.akun_id', $kas)
            ->whereExists(fn ($query) => $query->select(DB::raw(1))
                ->from('jurnal_detail as k')
                ->whereColumn('k.jurnal_id', 'j.id')
                ->whereIn('k.akun_id', $kas))
            ->groupBy('a.id', 'a.kode', 'a.nama')
            ->orderBy('a.kode')
            ->get(['a.id', 'a.kode', 'a.nama', DB::raw('SUM(d.kredit) as masuk'), DB::raw('SUM(d.debit) as keluar')]);

        $baris = fn (string $kolom) => $lawan
            ->map(fn ($item) => ['id' => $item->id, 'kode' => $item->kode, 'nama' => $item->nama, 'nilai' => Money::of((string) $item->{$kolom})])
            ->filter(fn (array $item) => Money::isPositive($item['nilai']))
            ->values()
            ->all();

        $penerimaan = $baris('masuk');
        $pengeluaran = $baris('keluar');
        $totalPenerimaan = $this->total($penerimaan);
        $totalPengeluaran = $this->total($pengeluaran);

        return [
            'saldo_awal' => $saldoAwal,
            'penerimaan' => $penerimaan,
            'pengeluaran' => $pengeluaran,
            'total_penerimaan' => $totalPenerimaan,
            'total_pengeluaran' => $totalPengeluaran,
            'kenaikan' => Money::sub($totalPenerimaan, $totalPengeluaran),
            'saldo_akhir' => $this->saldoKas($kas, $sampai),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function neracaSaldo(string $tanggal): array
    {
        $akun = $this->tampilkan($this->saldoAkun(null, $tanggal))
            ->map(function (array $item) {
                // Saldo yang berlawanan dengan saldo normal ditampilkan di kolom seberang.
                $debit = $item['normal_debit'] === ! Money::isNegative($item['nilai']);
                $nilai = Money::isNegative($item['nilai']) ? Money::negate($item['nilai']) : $item['nilai'];

                return [...$item, 'debit' => $debit ? $nilai : '0.00', 'kredit' => $debit ? '0.00' : $nilai];
            })
            ->values();

        $totalDebit = Money::add(...$akun->pluck('debit')->all());
        $totalKredit = Money::add(...$akun->pluck('kredit')->all());

        return [
            'akun' => $akun->all(),
            'total_debit' => $totalDebit,
            'total_kredit' => $totalKredit,
            'seimbang' => Money::compare($totalDebit, $totalKredit) === 0,
        ];
    }

    /**
     * Buku besar satu akun: saldo awal, mutasi periode, dan saldo berjalan (sisi saldo normal).
     *
     * @return array<string, mixed>
     */
    public function bukuBesar(Akun $akun, string $dari, string $sampai): array
    {
        $normalDebit = $akun->saldoNormalDebit();
        $arah = fn (string $debit, string $kredit) => $normalDebit ? Money::sub($debit, $kredit) : Money::sub($kredit, $debit);

        $awal = DB::table('jurnal_detail as d')
            ->join('jurnal as j', 'j.id', '=', 'd.jurnal_id')
            ->where('d.akun_id', $akun->id)
            ->where('j.tanggal', '<=', $this->akhir($this->sebelum($dari)))
            ->first([DB::raw('SUM(d.debit) as debit'), DB::raw('SUM(d.kredit) as kredit')]);

        $saldo = $arah(Money::of((string) $awal->debit), Money::of((string) $awal->kredit));
        $saldoAwal = $saldo;

        $baris = DB::table('jurnal_detail as d')
            ->join('jurnal as j', 'j.id', '=', 'd.jurnal_id')
            ->where('d.akun_id', $akun->id)
            ->whereBetween('j.tanggal', [$dari, $this->akhir($sampai)])
            ->orderBy('j.tanggal')
            ->orderBy('j.nomor')
            ->orderBy('d.urut')
            ->get(['j.id as jurnal_id', 'j.nomor', 'j.tanggal', 'j.keterangan', 'd.keterangan as keterangan_baris', 'd.debit', 'd.kredit'])
            ->map(function ($item) use (&$saldo, $arah) {
                $debit = Money::of((string) $item->debit);
                $kredit = Money::of((string) $item->kredit);
                $saldo = Money::add($saldo, $arah($debit, $kredit));

                return [
                    'jurnal_id' => $item->jurnal_id,
                    'nomor' => $item->nomor,
                    'tanggal' => substr((string) $item->tanggal, 0, 10),
                    'keterangan' => $item->keterangan_baris ?: $item->keterangan,
                    'debit' => $debit,
                    'kredit' => $kredit,
                    'saldo' => $saldo,
                ];
            });

        return [
            'akun' => ['id' => $akun->id, 'kode' => $akun->kode, 'nama' => $akun->nama, 'normal_debit' => $normalDebit],
            'saldo_awal' => $saldoAwal,
            'baris' => $baris->all(),
            'total_debit' => Money::add(...$baris->pluck('debit')->all()),
            'total_kredit' => Money::add(...$baris->pluck('kredit')->all()),
            'saldo_akhir' => $saldo,
        ];
    }

    /**
     * Saldo setiap akun (sisi saldo normal) dari jurnal pada rentang tanggal.
     *
     * @return Collection<int, array{id: string, kode: string, nama: string, kategori: string, aktif: bool, normal_debit: bool, nilai: string}>
     */
    protected function saldoAkun(?string $dari, string $sampai): Collection
    {
        $mutasi = DB::table('jurnal_detail as d')
            ->join('jurnal as j', 'j.id', '=', 'd.jurnal_id')
            ->when($dari, fn ($query) => $query->where('j.tanggal', '>=', $dari))
            ->where('j.tanggal', '<=', $this->akhir($sampai))
            ->groupBy('d.akun_id')
            ->get(['d.akun_id', DB::raw('SUM(d.debit) as debit'), DB::raw('SUM(d.kredit) as kredit')])
            ->keyBy('akun_id');

        return Akun::query()->orderBy('kode')->get()->map(function (Akun $akun) use ($mutasi) {
            $debit = Money::of((string) ($mutasi[$akun->id]->debit ?? '0'));
            $kredit = Money::of((string) ($mutasi[$akun->id]->kredit ?? '0'));

            return [
                'id' => $akun->id,
                'kode' => $akun->kode,
                'nama' => $akun->nama,
                'kategori' => $akun->kategori,
                'aktif' => $akun->is_active,
                'normal_debit' => $akun->saldoNormalDebit(),
                'nilai' => $akun->saldoNormalDebit() ? Money::sub($debit, $kredit) : Money::sub($kredit, $debit),
            ];
        });
    }

    /**
     * Akun nonaktif bersaldo nol tidak ditampilkan.
     *
     * @param  Collection<int, array<string, mixed>>  $akun
     * @return Collection<int, array<string, mixed>>
     */
    protected function tampilkan(Collection $akun): Collection
    {
        return $akun->filter(fn (array $item) => $item['aktif'] || Money::compare($item['nilai'], 0) !== 0)->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $akun
     */
    protected function laba(Collection $akun): string
    {
        return Money::sub(
            $this->total($akun->where('kategori', 'pendapatan')),
            $this->total($akun->where('kategori', 'beban')),
        );
    }

    /**
     * @param  Collection<int, array<string, mixed>>|array<int, array<string, mixed>>  $baris
     */
    protected function total(Collection|array $baris): string
    {
        return Money::add(...collect($baris)->pluck('nilai')->all());
    }

    /**
     * @param  Collection<int, string>  $kas
     */
    protected function saldoKas(Collection $kas, string $sampai): string
    {
        $saldo = DB::table('jurnal_detail as d')
            ->join('jurnal as j', 'j.id', '=', 'd.jurnal_id')
            ->whereIn('d.akun_id', $kas)
            ->where('j.tanggal', '<=', $this->akhir($sampai))
            ->first([DB::raw('SUM(d.debit) as debit'), DB::raw('SUM(d.kredit) as kredit')]);

        return Money::sub((string) ($saldo->debit ?? '0'), (string) ($saldo->kredit ?? '0'));
    }

    /**
     * Batas atas rentang tanggal. SQLite menyimpan kolom date sebagai "Y-m-d H:i:s",
     * jadi pembanding "Y-m-d" saja akan melewatkan hari terakhir.
     */
    protected function akhir(string $tanggal): string
    {
        return $tanggal.' 23:59:59';
    }

    protected function sebelum(string $tanggal): string
    {
        return date('Y-m-d', strtotime($tanggal.' -1 day'));
    }
}
