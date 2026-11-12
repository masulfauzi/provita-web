<?php

namespace App\Http\Modules\IbNasabah\Services;

use App\Http\Modules\IbNasabah\Models\AkunNasabah;
use App\Http\Modules\IbNasabah\Models\LoginNasabah;
use App\Http\Modules\IbNasabah\Requests\SimpanProfilRequest;
use App\Http\Modules\Nasabah\Models\Nasabah;
use App\Http\Modules\Nasabah\Services\NasabahService;
use App\Http\Modules\Rekening\Models\Rekening;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AkunNasabahService
{
    /**
     * Bentuk baku No. HP: angka saja, awalan +62/62 menjadi 0, mis. "+62 812-3456-7890" → "081234567890".
     */
    public function normalisasiHp(?string $noHp): string
    {
        $angka = preg_replace('/\D/', '', (string) $noHp);

        return str_starts_with($angka, '62') ? '0'.substr($angka, 2) : $angka;
    }

    /**
     * Akun dari No. HP login (format bebas, dinormalisasi).
     */
    public function cariAkun(string $noHp): ?AkunNasabah
    {
        $noHp = $this->normalisasiHp($noHp);

        return $noHp === '' ? null : AkunNasabah::query()->with('nasabah')->where('no_hp', $noHp)->first();
    }

    /**
     * Nasabah aktif yang cocok untuk aktivasi: identitas = NISN atau No. nasabah (awalan "NSB-"
     * boleh tidak ditulis) dan nomor rekening (belum ditutup) adalah miliknya.
     */
    public function cariNasabahAktivasi(string $identitas, string $noRekening): ?Nasabah
    {
        $identitas = trim($identitas);
        $nomor = strtoupper($identitas);
        $noRekening = trim($noRekening);

        if ($identitas === '' || $noRekening === '') {
            return null;
        }

        $kandidat = Nasabah::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('nisn', $identitas)
                ->orWhere('nomor', $nomor)
                ->orWhere('nomor', NasabahService::PREFIX_NOMOR.$nomor))
            ->pluck('id');

        if ($kandidat->isEmpty()) {
            return null;
        }

        $nasabahId = Rekening::query()
            ->whereIn('nasabah_id', $kandidat)
            ->where('nomor', $noRekening)
            ->where('status', '!=', Rekening::TUTUP)
            ->value('nasabah_id');

        return $nasabahId ? Nasabah::find($nasabahId) : null;
    }

    /**
     * Buat akun dari aktivasi sekaligus menyimpan data pribadi. Null bila nasabah ternyata sudah
     * punya akun (aktivasi bersamaan).
     *
     * @param  array<string, mixed>  $data  field profil + no_hp + password
     */
    public function aktivasi(Nasabah $nasabah, array $data): ?AkunNasabah
    {
        return DB::transaction(function () use ($nasabah, $data) {
            Nasabah::query()->whereKey($nasabah->id)->lockForUpdate()->first();

            if ($nasabah->akunIb()->exists()) {
                return null;
            }

            $akun = $nasabah->akunIb()->create([
                'no_hp' => $data['no_hp'],
                'password' => $data['password'],
                'diaktivasi_at' => now(),
                'password_diganti_at' => now(),
                'is_active' => true,
            ]);

            $this->simpanProfil($akun, $data, 'aktivasi', 'Aktivasi Internet Banking');

            return $akun;
        });
    }

    /**
     * Simpan data pribadi nasabah dari IB (hanya field SimpanProfilRequest::FIELD + no_hp) dan
     * catat nilai lama/baru dengan penyebab akun nasabah.
     *
     * @param  array<string, mixed>  $data
     */
    public function simpanProfil(AkunNasabah $akun, array $data, string $event, string $pesan): void
    {
        $nasabah = $akun->nasabah;
        $data = collect($data)->only([...SimpanProfilRequest::FIELD, 'no_hp'])->all();

        $kolom = [
            ...collect($data)->except('wali_nama')->all(),
            'nama_orang_tua' => $data['wali_nama'] ?? null,
            'email' => filled($data['email'] ?? null) ? $data['email'] : null,
        ];
        $kolom['alamat'] = Nasabah::susunAlamat([...$nasabah->getAttributes(), ...$kolom]);

        DB::transaction(function () use ($akun, $nasabah, $kolom, $event, $pesan) {
            $lama = $nasabah->only(array_keys($kolom));
            // Log nasabah dicatat manual (nilai lama/baru) agar penyebabnya akun nasabah.
            activity()->withoutLogging(fn () => $nasabah->update($kolom));

            activity('nasabah')->performedOn($nasabah)->causedBy($akun)->event($event)
                ->withProperties(['old' => $lama, 'attributes' => $nasabah->only(array_keys($kolom))])
                ->log($pesan);
        });
    }

    /**
     * Ganti No. HP login; No. HP di data nasabah ikut diperbarui.
     */
    public function gantiNoHp(AkunNasabah $akun, string $noHp): void
    {
        DB::transaction(function () use ($akun, $noHp) {
            $lama = $akun->no_hp;
            $akun->forceFill(['no_hp' => $noHp])->save();
            activity()->withoutLogging(fn () => $akun->nasabah->update(['no_hp' => $noHp]));

            activity('nasabah')->performedOn($akun->nasabah)->causedBy($akun)->event('ganti_no_hp')
                ->withProperties(['old' => ['no_hp' => $lama], 'attributes' => ['no_hp' => $noHp]])
                ->log('No. HP login Internet Banking diganti');
        });
    }

    /**
     * Reset oleh petugas: akun dihapus (semua sesi otomatis tidak berlaku), nasabah harus
     * aktivasi ulang. False bila nasabah belum punya akun.
     */
    public function resetAkun(Nasabah $nasabah, User $petugas): bool
    {
        return DB::transaction(function () use ($nasabah, $petugas) {
            $akun = $nasabah->akunIb()->lockForUpdate()->first();

            if (! $akun) {
                return false;
            }

            $akun->delete();

            activity('nasabah')->performedOn($nasabah)->causedBy($petugas)->event('reset_akun_ib')
                ->withProperties(['nasabah' => $nasabah->nomor, 'no_hp' => $akun->no_hp])
                ->log('Akun Internet Banking direset petugas (wajib aktivasi ulang)');

            return true;
        });
    }

    /**
     * Nilai yang tidak boleh dipakai sebagai password: NIS, NISN, No. nasabah, nomor rekening, No. HP.
     *
     * @return list<string>
     */
    public function identitasTerlarang(Nasabah $nasabah, ?string $noHp = null): array
    {
        return array_values(array_filter([
            $nasabah->nis,
            $nasabah->nisn,
            $nasabah->nomor,
            $noHp,
            ...$nasabah->rekening()->pluck('nomor')->all(),
        ]));
    }

    public function catatLogin(AkunNasabah $akun, Request $request, bool $berhasil): void
    {
        LoginNasabah::create([
            'akun_nasabah_id' => $akun->id,
            'waktu' => now(),
            'ip' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'perangkat' => $this->perangkat($request->userAgent()),
            'berhasil' => $berhasil,
        ]);
    }

    /**
     * Ringkasan perangkat dari user agent, mis. "Chrome · Android".
     */
    public function perangkat(?string $userAgent): string
    {
        $ua = (string) $userAgent;

        $peramban = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'SamsungBrowser') => 'Samsung Internet',
            str_contains($ua, 'Firefox/') || str_contains($ua, 'FxiOS') => 'Firefox',
            str_contains($ua, 'Chrome/') || str_contains($ua, 'CriOS') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            default => 'Peramban lain',
        };

        $sistem = match (true) {
            str_contains($ua, 'Android') => 'Android',
            (bool) preg_match('/iPhone|iPad|iPod/', $ua) => 'iOS',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS X') || str_contains($ua, 'Macintosh') => 'macOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'Lainnya',
        };

        return "{$peramban} · {$sistem}";
    }

    /**
     * Hapus riwayat login lama; per akun hanya N entri terakhir yang disimpan.
     *
     * @return int jumlah baris yang dihapus
     */
    public function bersihkanRiwayatLogin(?int $simpan = null): int
    {
        $simpan ??= (int) config('ib.riwayat_login_disimpan', 50);
        $dihapus = 0;

        AkunNasabah::query()->whereHas('riwayatLogin')->select('id')->chunkById(500, function ($akun) use ($simpan, &$dihapus) {
            foreach ($akun as $item) {
                $batas = LoginNasabah::query()->where('akun_nasabah_id', $item->id)
                    ->orderByDesc('waktu')->skip($simpan)->take(1)->value('waktu');

                if ($batas) {
                    $dihapus += LoginNasabah::query()->where('akun_nasabah_id', $item->id)->where('waktu', '<=', $batas)->delete();
                }
            }
        });

        return $dihapus;
    }
}
