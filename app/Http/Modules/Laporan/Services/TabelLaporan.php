<?php

namespace App\Http\Modules\Laporan\Services;

use App\Support\Money;

/**
 * Mengubah data laporan menjadi baris tabel datar untuk Excel. Nominal dikirim
 * sebagai angka (float hanya di tahap ekspor) agar bisa dijumlah di spreadsheet.
 */
class TabelLaporan
{
    /** @var list<array<int, mixed>> */
    protected array $baris = [];

    /** @var list<int> nomor baris (1-based) yang dicetak tebal */
    protected array $tebal = [];

    /**
     * @param  array<string, mixed>  $data
     * @param  array{judul: string, periode: string, nama_bank: string, nama_sekolah: string|null}  $kop
     * @return array{baris: list<array<int, mixed>>, tebal: list<int>, kolom_angka: list<string>}
     */
    public static function buat(string $laporan, array $data, array $kop): array
    {
        $tabel = new self;

        $tabel->tambah([$kop['nama_bank']], true);
        if ($kop['nama_sekolah']) {
            $tabel->tambah([$kop['nama_sekolah']]);
        }
        $tabel->tambah([$kop['judul']], true);
        $tabel->tambah([$kop['periode']]);
        $tabel->tambah([]);

        $kolomAngka = match ($laporan) {
            'neraca' => $tabel->neraca($data),
            'laba-rugi' => $tabel->labaRugi($data),
            'arus-kas' => $tabel->arusKas($data),
            'neraca-saldo' => $tabel->neracaSaldo($data),
            'buku-besar' => $tabel->bukuBesar($data),
            'harian' => $tabel->harian($data),
        };

        return ['baris' => $tabel->baris, 'tebal' => $tabel->tebal, 'kolom_angka' => $kolomAngka];
    }

    /**
     * @param  array<int, mixed>  $sel
     */
    protected function tambah(array $sel, bool $tebal = false): void
    {
        $this->baris[] = $sel;

        if ($tebal) {
            $this->tebal[] = count($this->baris);
        }
    }

    /**
     * @param  iterable<array<string, mixed>>  $akun
     */
    protected function daftarAkun(iterable $akun): void
    {
        foreach ($akun as $item) {
            $this->tambah([$item['kode'], $item['nama'], (float) $item['nilai']]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    protected function neraca(array $data): array
    {
        $this->tambah(['Kode', 'Akun', 'Jumlah'], true);
        $this->tambah(['ASET'], true);
        $this->daftarAkun($data['aset']);
        $this->tambah(['', 'Total aset', (float) $data['total_aset']], true);
        $this->tambah([]);
        $this->tambah(['KEWAJIBAN'], true);
        $this->daftarAkun($data['kewajiban']);
        $this->tambah(['', 'Total kewajiban', (float) $data['total_kewajiban']], true);
        $this->tambah(['EKUITAS'], true);
        $this->daftarAkun($data['ekuitas']);
        $this->tambah(['', 'Laba berjalan', (float) $data['laba_berjalan']]);
        $this->tambah(['', 'Total ekuitas', (float) $data['total_ekuitas']], true);
        $this->tambah(['', 'Total kewajiban & ekuitas', (float) $data['total_pasiva']], true);

        return ['C'];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    protected function labaRugi(array $data): array
    {
        $this->tambah(['Kode', 'Akun', 'Jumlah'], true);
        $this->tambah(['PENDAPATAN'], true);
        $this->daftarAkun($data['pendapatan']);
        $this->tambah(['', 'Total pendapatan', (float) $data['total_pendapatan']], true);
        $this->tambah(['BEBAN'], true);
        $this->daftarAkun($data['beban']);
        $this->tambah(['', 'Total beban', (float) $data['total_beban']], true);
        $this->tambah([]);
        $this->tambah(['', Money::isNegative($data['laba']) ? 'Rugi bersih' : 'Laba bersih', (float) $data['laba']], true);

        return ['C'];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    protected function arusKas(array $data): array
    {
        $this->tambah(['', 'Saldo kas awal periode', (float) $data['saldo_awal']], true);
        $this->tambah(['PENERIMAAN KAS'], true);
        $this->daftarAkun($data['penerimaan']);
        $this->tambah(['', 'Total penerimaan', (float) $data['total_penerimaan']], true);
        $this->tambah(['PENGELUARAN KAS'], true);
        $this->daftarAkun($data['pengeluaran']);
        $this->tambah(['', 'Total pengeluaran', (float) $data['total_pengeluaran']], true);
        $this->tambah([]);
        $this->tambah(['', 'Kenaikan (penurunan) kas', (float) $data['kenaikan']]);
        $this->tambah(['', 'Saldo kas akhir periode', (float) $data['saldo_akhir']], true);

        return ['C'];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    protected function neracaSaldo(array $data): array
    {
        $this->tambah(['Kode', 'Akun', 'Debit', 'Kredit'], true);

        foreach ($data['akun'] as $item) {
            $this->tambah([$item['kode'], $item['nama'], (float) $item['debit'], (float) $item['kredit']]);
        }

        $this->tambah(['', 'Total', (float) $data['total_debit'], (float) $data['total_kredit']], true);

        return ['C', 'D'];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    protected function bukuBesar(array $data): array
    {
        $this->tambah(["Akun {$data['akun']['kode']} {$data['akun']['nama']}"], true);
        $this->tambah(['Tanggal', 'Nomor jurnal', 'Keterangan', 'Debit', 'Kredit', 'Saldo'], true);
        $this->tambah(['', '', 'Saldo awal', null, null, (float) $data['saldo_awal']]);

        foreach ($data['baris'] as $item) {
            $this->tambah([$item['tanggal'], $item['nomor'], $item['keterangan'], (float) $item['debit'], (float) $item['kredit'], (float) $item['saldo']]);
        }

        $this->tambah(['', '', 'Total / saldo akhir', (float) $data['total_debit'], (float) $data['total_kredit'], (float) $data['saldo_akhir']], true);

        return ['D', 'E', 'F'];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    protected function harian(array $data): array
    {
        $this->tambah(['', 'Modal awal', (float) $data['modal_awal']], true);

        foreach (['pemasukan' => 'PEMASUKAN', 'pengeluaran' => 'PENGELUARAN'] as $kunci => $judul) {
            $this->tambah([$judul], true);

            foreach ($data[$kunci] as $item) {
                $this->tambah([$item['kode'], "{$item['nama']} ({$item['jumlah']} transaksi)", (float) $item['nilai']]);
            }

            $this->tambah(['', 'Total '.strtolower($judul), (float) $data["total_{$kunci}"]], true);
        }

        $this->tambah([]);
        $this->tambah(['', 'Saldo kas akhir', (float) $data['saldo_akhir']], true);

        if ($data['sesi']['ditutup_at'] ?? null) {
            $this->tambah(['', 'Uang fisik', (float) $data['sesi']['uang_fisik']]);
            $this->tambah(['', 'Selisih', (float) $data['sesi']['selisih']], true);

            if ($data['sesi']['keterangan']) {
                $this->tambah(['', "Keterangan: {$data['sesi']['keterangan']}"]);
            }
        }

        $this->tambah([]);
        $this->tambah(['RINCIAN TRANSAKSI'], true);
        $this->tambah(['Waktu', 'Jenis', 'Referensi', 'Nasabah', 'Keterangan', 'Masuk', 'Keluar', 'Petugas'], true);

        foreach ($data['rincian'] as $item) {
            $this->tambah([
                $item['waktu'], $item['jenis'], $item['referensi'], $item['nasabah'], $item['keterangan'],
                $item['masuk'] === null ? null : (float) $item['masuk'],
                $item['keluar'] === null ? null : (float) $item['keluar'],
                $item['petugas'],
            ]);
        }

        return ['C', 'F', 'G'];
    }
}
