<?php

namespace App\Http\Modules\Nasabah\Services;

use App\Http\Modules\Akuntansi\Models\Akun;
use App\Http\Modules\Akuntansi\Services\JurnalService;
use App\Http\Modules\Nasabah\Models\Nasabah;
use App\Http\Modules\Rekening\Models\JenisRekening;
use App\Http\Modules\Rekening\Models\Rekening;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

/**
 * Impor sekali pakai dari sistem lama: satu baris Excel = satu rekening. Siswa dikelompokkan
 * per NISN (nama, kelas, NIS dari baris Tabungan Pelajar); guru/karyawan/lembaga satu baris
 * satu nasabah. Nomor rekening = kolom nipd (nomor lama), atau prefix jenis + NISN bila kosong.
 *
 * Saldo masuk sebagai saldo_awal rekening (tanpa baris transaksi, jadi tidak muncul di sesi kas
 * maupun buku tabungan), lalu satu jurnal D Kas / K Simpanan sebesar total saldo.
 */
class ImporNasabahService
{
    public const KOLOM = ['kelas', 'nipd', 'nisn', 'nama_nasabah', 'saldo', 'jenis_nasabah', 'jenis_rekening'];

    /** Nama jenis rekening di sistem lama yang berbeda dengan nama di sistem ini => kode jenis. */
    public const ALIAS_REKENING = ['tabungan katalog' => 'foto_katalog'];

    public function __construct(
        protected JurnalService $jurnal,
        protected NasabahService $nasabah,
    ) {}

    /**
     * Membaca & memeriksa file tanpa menyimpan apa pun.
     *
     * @return array{nasabah: list<array<string, mixed>>, error: list<array{baris: int|null, pesan: string}>, peringatan: list<array{baris: int|null, pesan: string}>, ringkasan: array<string, mixed>}
     */
    public function periksa(string $path): array
    {
        $error = [];
        $peringatan = [];
        $baris = $this->baca($path, $error);

        $jenisRekening = JenisRekening::query()->get()->keyBy(fn (JenisRekening $jenis) => mb_strtolower($jenis->nama));
        $alias = collect(self::ALIAS_REKENING)->map(fn ($kode) => JenisRekening::query()->where('kode', $kode)->first())->filter();

        // 1. Normalisasi per baris.
        $valid = [];

        foreach ($baris as $no => $data) {
            $salah = function (string $pesan) use (&$error, $no): void {
                $error[] = ['baris' => $no, 'pesan' => $pesan];
            };
            $jenis = mb_strtolower($data['jenis_nasabah'] ?? '');
            $namaRekening = mb_strtolower($data['jenis_rekening'] ?? '');
            $rekening = $jenisRekening->get($namaRekening) ?? $alias->get($namaRekening);
            $saldo = $data['saldo'] ?? null;

            if (! array_key_exists($jenis, Nasabah::JENIS)) {
                $salah("Jenis nasabah \"{$data['jenis_nasabah']}\" tidak dikenal (siswa, guru, karyawan, lembaga).");

                continue;
            }

            if (! $rekening) {
                $salah("Jenis rekening \"{$data['jenis_rekening']}\" tidak ada di sistem.");

                continue;
            }

            if (! $rekening->is_active) {
                $salah("Jenis rekening {$rekening->nama} tidak aktif.");

                continue;
            }

            if (($data['nama_nasabah'] ?? '') === '') {
                $salah('Nama nasabah kosong.');

                continue;
            }

            if ($saldo !== null && (! is_numeric($saldo) || ! preg_match('/^\d+(\.\d{1,2})?$/', (string) $saldo))) {
                $salah("Saldo \"{$saldo}\" tidak valid (harus angka tidak negatif, maksimal 2 desimal).");

                continue;
            }

            $nipd = $data['nipd'];
            $nisn = $data['nisn'];

            if ($nipd !== null && ! ctype_digit($nipd)) {
                $salah("Kolom nipd \"{$nipd}\" harus berupa angka.");

                continue;
            }

            if ($nisn !== null && (! ctype_digit($nisn) || strlen($nisn) > 10)) {
                $salah("NISN \"{$nisn}\" harus berupa angka maksimal 10 digit.");

                continue;
            }

            if ($jenis === 'siswa' && $nisn === null) {
                $salah('Siswa wajib punya NISN.');

                continue;
            }

            $valid[$no] = [
                'baris' => $no,
                'jenis' => $jenis,
                'nama' => $data['nama_nasabah'],
                'kelas' => $data['kelas'],
                'nipd' => $nipd,
                'nisn' => $jenis === 'siswa' ? str_pad($nisn, 10, '0', STR_PAD_LEFT) : null,
                'saldo' => Money::of($saldo ?? '0'),
                'rekening' => $rekening,
            ];
        }

        // nipd adalah nomor rekening lama, jadi harus unik di file.
        foreach (collect($valid)->whereNotNull('nipd')->groupBy('nipd')->filter(fn ($grup) => $grup->count() > 1) as $nipd => $grup) {
            $error[] = ['baris' => $grup->first()['baris'], 'pesan' => "Nomor rekening (nipd) {$nipd} dipakai lebih dari satu baris: ".$grup->pluck('baris')->join(', ').'.'];
        }

        // 2. Kelompokkan menjadi nasabah.
        $nasabah = [];

        foreach (collect($valid)->where('jenis', 'siswa')->groupBy('nisn') as $nisn => $grup) {
            $pelajar = $grup->filter(fn ($r) => $r['rekening']->kode === JenisRekening::TABUNGAN)->values();

            if ($pelajar->count() > 1) {
                $error[] = ['baris' => $pelajar[0]['baris'], 'pesan' => "NISN {$nisn} punya lebih dari satu rekening Tabungan Pelajar (baris ".$pelajar->pluck('baris')->join(', ').').'];

                continue;
            }

            $utama = $pelajar->first();

            if (! $utama) {
                $peringatan[] = ['baris' => $grup->first()['baris'], 'pesan' => "NISN {$nisn} tidak punya rekening Tabungan Pelajar; dibuat nasabah baru tanpa kelas."];
            }

            foreach ($grup as $r) {
                if (! $utama || $r === $utama) {
                    continue;
                }

                if ($this->namaBerbeda($utama['nama'], $r['nama'])) {
                    $peringatan[] = ['baris' => $r['baris'], 'pesan' => "NISN sama dengan baris {$utama['baris']} tetapi nama sangat berbeda; dianggap orang yang sama."];
                }

                if ($r['kelas'] !== null && $r['kelas'] !== $utama['kelas']) {
                    $peringatan[] = ['baris' => $r['baris'], 'pesan' => "Kelas {$r['kelas']} berbeda dengan baris Tabungan Pelajar ({$utama['kelas']}); dipakai {$utama['kelas']}."];
                }
            }

            $nasabah[] = [
                'jenis' => 'siswa',
                'nama' => $utama ? $utama['nama'] : $this->buangLabel($grup->first()['nama']),
                'nisn' => $nisn,
                'nis' => $utama['nipd'] ?? null,
                'kelas' => $utama['kelas'] ?? null,
                'baris' => $grup->pluck('baris')->all(),
                'rekening' => $grup->sortBy(fn ($r) => $r['rekening']->kode === JenisRekening::TABUNGAN ? 0 : 1)->values()->all(),
            ];
        }

        $namaLain = [];

        foreach (collect($valid)->where('jenis', '!=', 'siswa') as $r) {
            $kunci = mb_strtolower($r['nama']);

            if (isset($namaLain[$kunci])) {
                $peringatan[] = ['baris' => $r['baris'], 'pesan' => "Nama sama dengan baris {$namaLain[$kunci]}; dibuat sebagai nasabah terpisah."];
            }

            $namaLain[$kunci] ??= $r['baris'];

            $nasabah[] = [
                'jenis' => $r['jenis'],
                'nama' => $r['nama'],
                'nisn' => null,
                'nis' => null,
                'kelas' => $r['jenis'] === Nasabah::LEMBAGA ? null : $r['kelas'],
                'baris' => [$r['baris']],
                'rekening' => [$r],
            ];
        }

        foreach ($nasabah as $item) {
            foreach (['nama' => 255, 'kelas' => 30] as $kolom => $maks) {
                if (mb_strlen((string) $item[$kolom]) > $maks) {
                    $error[] = ['baris' => $item['baris'][0], 'pesan' => ucfirst($kolom)." melebihi {$maks} karakter."];
                }
            }
        }

        // 3. Bentrok dengan data yang sudah ada (sekaligus mencegah impor dua kali).
        $nisnAda = Nasabah::query()->whereIn('nisn', array_filter(array_column($nasabah, 'nisn')))->pluck('nisn')->flip();
        $nisAda = Nasabah::query()->whereIn('nis', array_filter(array_column($nasabah, 'nis')))->pluck('nis')->flip();
        $nipdAda = Rekening::query()->whereIn('nomor', collect($valid)->pluck('nipd')->filter()->all())->pluck('nomor')->flip();

        foreach ($nasabah as $item) {
            if ($item['nisn'] && $nisnAda->has($item['nisn'])) {
                $error[] = ['baris' => $item['baris'][0], 'pesan' => "NISN {$item['nisn']} sudah terdaftar sebagai nasabah."];
            }

            if ($item['nis'] && $nisAda->has($item['nis'])) {
                $error[] = ['baris' => $item['baris'][0], 'pesan' => "NIS {$item['nis']} sudah terdaftar sebagai nasabah."];
            }
        }

        foreach ($valid as $r) {
            if ($r['nipd'] && $nipdAda->has($r['nipd'])) {
                $error[] = ['baris' => $r['baris'], 'pesan' => "Nomor rekening {$r['nipd']} sudah ada di sistem."];
            }
        }

        // 4. Nomor rekening: nipd, atau prefix jenis + NISN bila nipd kosong.
        $terpakai = collect($valid)->pluck('nipd')->filter()->flip()->all();

        foreach ($nasabah as &$item) {
            foreach ($item['rekening'] as &$r) {
                if ($r['nipd']) {
                    $r['nomor'] = $r['nipd'];

                    continue;
                }

                if (! $item['nisn']) {
                    $error[] = ['baris' => $r['baris'], 'pesan' => 'Kolom nipd (nomor rekening) kosong dan nasabah tidak punya NISN.'];
                    $r['nomor'] = null;

                    continue;
                }

                $r['nomor'] = $this->nomorBebas(($r['rekening']->prefix ?? '').$item['nisn'], $terpakai);
                $terpakai[$r['nomor']] = true;
            }
        }
        unset($item, $r);

        usort($error, fn ($a, $b) => $a['baris'] <=> $b['baris']);
        usort($peringatan, fn ($a, $b) => $a['baris'] <=> $b['baris']);

        return [
            'nasabah' => $nasabah,
            'error' => $error,
            'peringatan' => $peringatan,
            'ringkasan' => $this->ringkasan($nasabah, count($baris)),
        ];
    }

    /**
     * Menyimpan hasil periksa() dalam satu DB transaction. Ditolak seluruhnya bila ada error.
     *
     * @return array<string, mixed> ringkasan beserta nomor jurnal saldo awal
     *
     * @throws ValidationException
     */
    public function impor(string $path, Carbon|string $tanggal, ?User $user = null, ?string $namaFile = null): array
    {
        $hasil = $this->periksa($path);

        if ($hasil['error'] !== []) {
            throw ValidationException::withMessages([
                'file' => 'File masih berisi '.count($hasil['error']).' error. Perbaiki lalu periksa ulang.',
            ]);
        }

        $tanggal = Carbon::parse($tanggal)->toDateString();
        $ringkasan = $hasil['ringkasan'];

        $jurnal = DB::transaction(fn () => activity()->withoutLogging(function () use ($hasil, $tanggal, $ringkasan, $user) {
            foreach ($hasil['nasabah'] as $item) {
                $nasabah = new Nasabah([
                    'nama' => $item['nama'],
                    'jenis' => $item['jenis'],
                    'nisn' => $item['nisn'],
                    'nis' => $item['nis'],
                    'kelas' => $item['kelas'],
                    'is_active' => true,
                    'keterangan' => 'Impor sistem lama',
                ]);
                $nasabah->nomor = $this->nasabah->nomorBerikutnya();
                $nasabah->save();

                foreach ($item['rekening'] as $r) {
                    // saldo tidak fillable: hanya diatur sistem.
                    $nasabah->rekening()->make([
                        'nomor' => $r['nomor'],
                        'jenis_rekening_id' => $r['rekening']->id,
                        'saldo_awal' => $r['saldo'],
                        'status' => Rekening::AKTIF,
                        'tanggal_buka' => $tanggal,
                    ])->forceFill(['saldo' => $r['saldo']])->save();
                }
            }

            if (! Money::isPositive($ringkasan['total_saldo'])) {
                return null;
            }

            return $this->jurnal->catat($tanggal, "Saldo awal migrasi sistem lama ({$ringkasan['jumlah_rekening']} rekening)", [
                ['akun_id' => Akun::peran(Akun::KAS)->id, 'debit' => $ringkasan['total_saldo']],
                ['akun_id' => Akun::peran(Akun::SIMPANAN)->id, 'kredit' => $ringkasan['total_saldo']],
            ], $user);
        }), 3);

        activity('impor')->causedBy($user)->event('impor')
            ->withProperties([...$ringkasan, 'file' => $namaFile, 'tanggal' => $tanggal, 'jurnal' => $jurnal?->nomor])
            ->log('Impor data nasabah sistem lama');

        return [...$ringkasan, 'jurnal' => $jurnal?->nomor];
    }

    /**
     * Baris data (nomor baris Excel => kolom yang sudah di-trim). Kolom wajib yang hilang
     * dicatat sebagai error.
     *
     * @param  list<array{baris: int|null, pesan: string}>  $error
     * @return array<int, array<string, string|null>>
     */
    protected function baca(string $path, array &$error): array
    {
        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $rows = $reader->load($path)->getSheet(0)->toArray(null, true, false, false);
        } catch (Throwable) {
            $error[] = ['baris' => null, 'pesan' => 'File tidak bisa dibaca. Pastikan formatnya .xlsx.'];

            return [];
        }

        $header = array_map(fn ($h) => mb_strtolower(trim((string) $h)), array_shift($rows) ?? []);
        $posisi = array_flip(array_filter($header, fn ($h) => $h !== ''));

        if ($hilang = array_diff(self::KOLOM, array_keys($posisi))) {
            $error[] = ['baris' => 1, 'pesan' => 'Kolom wajib tidak ditemukan: '.implode(', ', $hilang).'.'];

            return [];
        }

        $baris = [];

        foreach ($rows as $i => $row) {
            $data = [];

            foreach (self::KOLOM as $kolom) {
                $nilai = $row[$posisi[$kolom]] ?? null;
                $nilai = $nilai === null ? '' : trim(preg_replace('/\s+/u', ' ', (string) $nilai));
                $data[$kolom] = $nilai === '' ? null : $nilai;
            }

            if (array_filter($data, fn ($v) => $v !== null) === []) {
                continue;
            }

            $baris[$i + 2] = $data;
        }

        if ($baris === []) {
            $error[] = ['baris' => null, 'pesan' => 'File tidak berisi data.'];
        }

        return $baris;
    }

    /**
     * @param  list<array<string, mixed>>  $nasabah
     * @return array<string, mixed>
     */
    protected function ringkasan(array $nasabah, int $jumlahBaris): array
    {
        $rekening = collect($nasabah)->flatMap(fn ($item) => $item['rekening']);

        return [
            'jumlah_baris' => $jumlahBaris,
            'jumlah_nasabah' => count($nasabah),
            'jumlah_rekening' => $rekening->count(),
            'total_saldo' => Money::add('0', ...$rekening->pluck('saldo')->all()),
            'nasabah_per_jenis' => collect($nasabah)->countBy('jenis')
                ->map(fn ($jumlah, $jenis) => ['jenis' => Nasabah::JENIS[$jenis], 'jumlah' => $jumlah])->values()->all(),
            'rekening_per_jenis' => $rekening->groupBy(fn ($r) => $r['rekening']->nama)
                ->map(fn ($grup, $jenis) => ['jenis' => $jenis, 'jumlah' => $grup->count(), 'saldo' => Money::add('0', ...$grup->pluck('saldo')->all())])
                ->values()->all(),
        ];
    }

    /**
     * Nomor dengan suffix urut bila sudah dipakai di file atau di database.
     *
     * @param  array<string, mixed>  $terpakai
     */
    protected function nomorBebas(string $nomor, array $terpakai): string
    {
        $diDb = Rekening::query()->where('nomor', 'like', $nomor.'%')->pluck('nomor')->flip()->all();
        $kandidat = $nomor;

        for ($suffix = 1; isset($terpakai[$kandidat]) || isset($diDb[$kandidat]); $suffix++) {
            $kandidat = $nomor.$suffix;
        }

        return $kandidat;
    }

    /**
     * Nama di baris non-Pelajar sering diberi label, mis. "Budi (Tab. Wisata)".
     */
    protected function buangLabel(string $nama): string
    {
        return trim(preg_replace('/\s*\([^)]*(tab|wisata|wisuda|outing|katalog)[^)]*\)\s*_?/iu', ' ', $nama)) ?: $nama;
    }

    /**
     * Nama dianggap berbeda bila tidak saling memuat, kata pertamanya beda, dan kemiripannya < 70%.
     */
    protected function namaBerbeda(string $a, string $b): bool
    {
        $a = mb_strtolower(trim($this->buangLabel($a)));
        $b = mb_strtolower(trim($this->buangLabel($b)));
        similar_text($a, $b, $persen);

        return ! ($a === $b || str_contains($a, $b) || str_contains($b, $a)
            || explode(' ', $a)[0] === explode(' ', $b)[0] || $persen >= 70);
    }
}
