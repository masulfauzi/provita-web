<?php

namespace App\Http\Modules\Autodebet\Services;

use App\Http\Modules\Autodebet\Models\Autodebet;
use App\Http\Modules\Autodebet\Models\AutodebetRiwayat;
use App\Http\Modules\Pengaturan\Services\PengaturanService;
use App\Http\Modules\Rekening\Models\Rekening;
use App\Http\Modules\Transaksi\Exceptions\TransaksiDitolakException;
use App\Http\Modules\Transaksi\Services\TransaksiService;
use App\Models\User;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Autodebet: pindah buku terjadwal dari Tabungan Pelajar (rekening utama) ke rekening
 * tujuan ber-prefix milik nasabah yang sama. Diproses saat sesi kas dibuka untuk semua
 * jadwal yang jatuh tempo s.d. tanggal transaksi.
 */
class AutodebetService
{
    public function __construct(
        protected TransaksiService $transaksi,
        protected PengaturanService $pengaturan,
    ) {}

    /**
     * @param  array{status?: string|null}  $filters
     */
    public function paginate(string $search = '', array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Autodebet::query()
            ->with(['rekening:id,nomor,nasabah_id,jenis_rekening_id,saldo', 'rekening.nasabah:id,nomor,nama,kelas', 'rekening.jenisRekening:id,nama', 'sumber:id,nomor,saldo'])
            ->when($search !== '', fn (Builder $query) => $query->whereHas('rekening', fn (Builder $r) => $r
                ->where('nomor', 'like', "%{$search}%")
                ->orWhereHas('nasabah', fn (Builder $n) => $n->where('nama', 'like', "%{$search}%")->orWhere('nomor', 'like', "%{$search}%"))))
            ->when($filters['status'] ?? null, fn (Builder $query, $status) => $query->where('status', $status))
            ->orderByRaw("case status when 'aktif' then 0 when 'berhenti' then 1 else 2 end")
            ->orderBy('jatuh_tempo')
            ->paginate(min(max($perPage, 5), 100))
            ->withQueryString();
    }

    /**
     * @param  array{rekening_id: string, sumber_rekening_id?: string|null, nominal: int|string, periode: string, hari: int, mulai?: string|null, target?: int|string|null, keterangan?: string|null}  $data
     *
     * @throws ValidationException
     */
    public function buat(array $data, ?User $user = null): Autodebet
    {
        $tujuan = Rekening::query()->with('jenisRekening')->findOrFail($data['rekening_id']);

        if (! $tujuan->isAktif() || $tujuan->jenisRekening?->prefix === null) {
            throw ValidationException::withMessages(['rekening_id' => 'Autodebet hanya untuk rekening aktif selain rekening utama (Tabungan Pelajar).']);
        }

        if (Autodebet::query()->where('rekening_id', $tujuan->id)->where('status', '!=', Autodebet::SELESAI)->exists()) {
            throw ValidationException::withMessages(['rekening_id' => "Rekening {$tujuan->nomor} sudah punya autodebet."]);
        }

        $sumber = $this->sumber($tujuan, $data['sumber_rekening_id'] ?? null);
        [$nominal, $target] = $this->nominalTarget($data['nominal'], $data['target'] ?? null);
        $this->pastikanHari($data['periode'], (int) $data['hari']);

        $mulai = Carbon::parse($data['mulai'] ?? $this->pengaturan->tanggalTransaksi()->addDay())->startOfDay();

        if ($mulai->lt($this->pengaturan->tanggalTransaksi())) {
            throw ValidationException::withMessages(['mulai' => 'Tanggal mulai tidak boleh sebelum tanggal transaksi aktif.']);
        }

        return Autodebet::create([
            'rekening_id' => $tujuan->id,
            'sumber_rekening_id' => $sumber->id,
            'nominal' => $nominal,
            'periode' => $data['periode'],
            'hari' => (int) $data['hari'],
            'mulai' => $mulai,
            'jatuh_tempo' => $this->jadwalPertama($data['periode'], (int) $data['hari'], $mulai),
            'target' => $target,
            'terkumpul' => '0.00',
            'status' => Autodebet::AKTIF,
            'keterangan' => $data['keterangan'] ?? null,
            'dibuat_oleh' => $user?->id,
        ]);
    }

    /**
     * @param  array{nominal: int|string, periode: string, hari: int, target?: int|string|null, keterangan?: string|null}  $data
     *
     * @throws ValidationException
     */
    public function ubah(Autodebet $autodebet, array $data): Autodebet
    {
        if ($autodebet->status === Autodebet::SELESAI) {
            throw ValidationException::withMessages(['autodebet' => 'Autodebet yang sudah selesai tidak bisa diubah.']);
        }

        [$nominal, $target] = $this->nominalTarget($data['nominal'], $data['target'] ?? null);
        $this->pastikanHari($data['periode'], (int) $data['hari']);

        if ($target !== null && Money::compare($target, $autodebet->terkumpul) <= 0) {
            throw ValidationException::withMessages(['target' => 'Target harus lebih besar dari yang sudah terkumpul ('.Money::format($autodebet->terkumpul).').']);
        }

        $jadwalBerubah = $data['periode'] !== $autodebet->periode || (int) $data['hari'] !== $autodebet->hari;

        $autodebet->update([
            'nominal' => $nominal,
            'target' => $target,
            'periode' => $data['periode'],
            'hari' => (int) $data['hari'],
            'keterangan' => $data['keterangan'] ?? null,
            // Jadwal baru dihitung dari tanggal transaksi berikutnya agar tidak menagih mundur.
            'jatuh_tempo' => $jadwalBerubah
                ? $this->jadwalPertama($data['periode'], (int) $data['hari'], $this->pengaturan->tanggalTransaksi()->addDay())
                : $autodebet->jatuh_tempo,
        ]);

        return $autodebet;
    }

    public function hentikan(Autodebet $autodebet): Autodebet
    {
        if ($autodebet->status === Autodebet::AKTIF) {
            $autodebet->update(['status' => Autodebet::BERHENTI]);
        }

        return $autodebet;
    }

    /**
     * Melanjutkan autodebet yang dihentikan; jadwal yang terlewat selama berhenti tidak ditagih.
     */
    public function lanjutkan(Autodebet $autodebet): Autodebet
    {
        if ($autodebet->status === Autodebet::BERHENTI) {
            $autodebet->update([
                'status' => Autodebet::AKTIF,
                'jatuh_tempo' => $this->jadwalPertama($autodebet->periode, $autodebet->hari, $this->pengaturan->tanggalTransaksi()->addDay()),
            ]);
        }

        return $autodebet;
    }

    /**
     * Memproses semua jadwal autodebet yang jatuh tempo s.d. tanggal (termasuk yang terlewat).
     *
     * @return array{berhasil: int, gagal: int, selesai: int, total: string}
     */
    public function proses(Carbon $tanggal, ?User $user = null): array
    {
        $hasil = ['berhasil' => 0, 'gagal' => 0, 'selesai' => 0, 'total' => '0.00'];

        $ids = Autodebet::query()->where('status', Autodebet::AKTIF)->whereDate('jatuh_tempo', '<=', $tanggal)->orderBy('jatuh_tempo')->pluck('id');

        foreach ($ids as $id) {
            // Satu jadwal per putaran, masing-masing dalam DB transaction sendiri.
            while ($this->prosesSatu($id, $tanggal, $user, $hasil)) {
                //
            }
        }

        return $hasil;
    }

    /**
     * Memproses jadwal terdekat satu autodebet. Mengembalikan false bila tidak ada lagi yang jatuh tempo.
     *
     * @param  array{berhasil: int, gagal: int, selesai: int, total: string}  $hasil
     */
    protected function prosesSatu(string $id, Carbon $tanggal, ?User $user, array &$hasil): bool
    {
        return DB::transaction(function () use ($id, $tanggal, $user, &$hasil) {
            $autodebet = Autodebet::query()->whereKey($id)->lockForUpdate()->first();

            if (! $autodebet || $autodebet->status !== Autodebet::AKTIF || $autodebet->jatuh_tempo->gt($tanggal)) {
                return false;
            }

            $jadwal = $autodebet->jatuh_tempo->copy();
            // Bila ada target, debet terakhir dibatasi sisa kekurangannya.
            $nominal = Money::of($autodebet->nominal);
            $sisa = $autodebet->target === null ? null : Money::sub($autodebet->target, $autodebet->terkumpul);

            if ($sisa !== null && Money::compare($sisa, $nominal) < 0) {
                $nominal = $sisa;
            }

            $riwayat = ['autodebet_id' => $autodebet->id, 'jadwal' => $jadwal->toDateString(), 'tanggal' => $tanggal->toDateString(), 'nominal' => $nominal];

            if (AutodebetRiwayat::query()->where('autodebet_id', $autodebet->id)->whereDate('jadwal', $jadwal)->exists()) {
                // Sudah pernah diproses (mis. proses ganda): cukup majukan jadwalnya.
                $autodebet->update(['jatuh_tempo' => $this->jadwalBerikutnya($autodebet->periode, $autodebet->hari, $jadwal)]);

                return true;
            }

            try {
                $pindah = $this->transaksi->pindahBuku(
                    Rekening::findOrFail($autodebet->sumber_rekening_id),
                    Rekening::findOrFail($autodebet->rekening_id),
                    $nominal,
                    $user,
                );

                AutodebetRiwayat::create([...$riwayat, 'status' => AutodebetRiwayat::BERHASIL, 'transaksi_keluar_id' => $pindah['keluar']->id, 'transaksi_masuk_id' => $pindah['masuk']->id]);
                $autodebet->terkumpul = Money::add($autodebet->terkumpul, $nominal);
                $hasil['berhasil']++;
                $hasil['total'] = Money::add($hasil['total'], $nominal);
            } catch (TransaksiDitolakException $e) {
                AutodebetRiwayat::create([...$riwayat, 'status' => AutodebetRiwayat::GAGAL, 'alasan' => mb_strimwidth($e->getMessage(), 0, 255)]);
                $hasil['gagal']++;
            }

            $autodebet->jatuh_tempo = $this->jadwalBerikutnya($autodebet->periode, $autodebet->hari, $jadwal);

            if ($autodebet->target !== null && Money::compare($autodebet->terkumpul, $autodebet->target) >= 0) {
                $autodebet->status = Autodebet::SELESAI;
                $hasil['selesai']++;
            }

            $autodebet->save();

            return $autodebet->status === Autodebet::AKTIF;
        }, 3);
    }

    /**
     * Jadwal pertama pada atau setelah tanggal mulai.
     */
    public function jadwalPertama(string $periode, int $hari, Carbon $mulai): Carbon
    {
        $mulai = $mulai->copy()->startOfDay();

        if ($periode === Autodebet::MINGGUAN) {
            return $mulai->addDays(($hari - $mulai->dayOfWeekIso + 7) % 7);
        }

        $jadwal = $mulai->copy()->setDay($hari);

        return $jadwal->lt($mulai) ? $jadwal->addMonthNoOverflow()->setDay($hari) : $jadwal;
    }

    public function jadwalBerikutnya(string $periode, int $hari, Carbon $jadwal): Carbon
    {
        return $periode === Autodebet::MINGGUAN
            ? $jadwal->copy()->addWeek()
            : $jadwal->copy()->startOfMonth()->addMonth()->setDay($hari);
    }

    /**
     * Sumber dana: Tabungan Pelajar (rekening utama) aktif milik nasabah yang sama.
     *
     * @throws ValidationException
     */
    protected function sumber(Rekening $tujuan, ?string $sumberId): Rekening
    {
        $calon = Rekening::query()
            ->where('nasabah_id', $tujuan->nasabah_id)
            ->where('status', Rekening::AKTIF)
            ->whereHas('jenisRekening', fn (Builder $j) => $j->whereNull('prefix'))
            ->orderBy('nomor')
            ->get();

        if ($sumberId !== null) {
            return $calon->firstWhere('id', $sumberId)
                ?? throw ValidationException::withMessages(['sumber_rekening_id' => 'Rekening sumber harus Tabungan Pelajar aktif milik nasabah yang sama.']);
        }

        return match ($calon->count()) {
            0 => throw ValidationException::withMessages(['sumber_rekening_id' => 'Nasabah belum punya Tabungan Pelajar aktif sebagai sumber dana.']),
            1 => $calon->first(),
            default => throw ValidationException::withMessages(['sumber_rekening_id' => 'Nasabah punya lebih dari satu Tabungan Pelajar; pilih rekening sumber.']),
        };
    }

    /**
     * @return array{0: string, 1: string|null}
     *
     * @throws ValidationException
     */
    protected function nominalTarget(int|string $nominal, int|string|null $target): array
    {
        $nominal = Money::of($nominal);
        $target = blank($target) ? null : Money::of($target);

        if (! Money::isPositive($nominal)) {
            throw ValidationException::withMessages(['nominal' => 'Nominal autodebet harus lebih dari nol.']);
        }

        if ($target !== null && Money::compare($target, $nominal) < 0) {
            throw ValidationException::withMessages(['target' => 'Target minimal sama dengan nominal autodebet.']);
        }

        return [$nominal, $target];
    }

    /**
     * @throws ValidationException
     */
    protected function pastikanHari(string $periode, int $hari): void
    {
        $batas = $periode === Autodebet::MINGGUAN ? 7 : 28;

        if ($hari < 1 || $hari > $batas) {
            throw ValidationException::withMessages(['hari' => $periode === Autodebet::MINGGUAN ? 'Pilih hari Senin s.d. Minggu.' : 'Tanggal autodebet 1 sampai 28.']);
        }
    }
}
