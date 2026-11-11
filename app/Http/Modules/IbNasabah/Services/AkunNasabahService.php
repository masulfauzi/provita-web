<?php

namespace App\Http\Modules\IbNasabah\Services;

use App\Http\Modules\IbNasabah\Models\AkunNasabah;
use App\Http\Modules\IbNasabah\Models\LoginNasabah;
use App\Http\Modules\Nasabah\Models\Nasabah;
use App\Http\Modules\Rekening\Models\Rekening;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AkunNasabahService
{
    /** Tanpa karakter yang mudah tertukar saat dibaca dari slip (0/O, 1/l/I). */
    protected const HURUF = 'abcdefghjkmnpqrstuvwxyz';

    protected const ANGKA = '23456789';

    /**
     * Akun dari identitas login: NIS, nomor nasabah, atau nomor rekening.
     */
    public function cariAkun(string $identitas): ?AkunNasabah
    {
        $identitas = trim($identitas);

        if ($identitas === '') {
            return null;
        }

        $nasabahId = Nasabah::query()->where('nis', $identitas)->orWhere('nomor', $identitas)->value('id')
            ?? Rekening::query()->where('nomor', $identitas)->value('nasabah_id');

        return $nasabahId ? AkunNasabah::query()->with('nasabah')->where('nasabah_id', $nasabahId)->first() : null;
    }

    /**
     * Membuat akun dengan password awal acak (wajib diganti saat login pertama).
     *
     * @return array{0: AkunNasabah, 1: string} akun dan password awal (teks, untuk dibagikan sekali)
     *
     * @throws ValidationException
     */
    public function buatAkun(Nasabah $nasabah): array
    {
        if ($nasabah->akunIb()->exists()) {
            throw ValidationException::withMessages(['nasabah' => "{$nasabah->nama} sudah punya akun Internet Banking."]);
        }

        $password = $this->passwordAcak();

        $akun = $nasabah->akunIb()->create([
            'password' => $password,
            'wajib_ganti_password' => true,
            'is_active' => true,
        ]);

        return [$akun, $password];
    }

    /**
     * Reset oleh petugas: password sementara, wajib ganti password, semua sesi dikeluarkan.
     * Akun dibuat bila nasabah belum punya.
     */
    public function resetOlehPetugas(Nasabah $nasabah, User $petugas): string
    {
        return DB::transaction(function () use ($nasabah, $petugas) {
            $password = $this->passwordAcak();
            $akun = $nasabah->akunIb()->lockForUpdate()->first();

            if ($akun) {
                $akun->forceFill([
                    'password' => $password,
                    'wajib_ganti_password' => true,
                    'versi_sesi' => $akun->versi_sesi + 1,
                ])->save();
            } else {
                $akun = $nasabah->akunIb()->create(['password' => $password, 'wajib_ganti_password' => true, 'is_active' => true]);
            }

            activity('nasabah')->performedOn($nasabah)->causedBy($petugas)->event('reset_password_ib')
                ->withProperties(['nasabah' => $nasabah->nomor])
                ->log('Password Internet Banking direset petugas');

            return $password;
        });
    }

    /**
     * Nilai yang tidak boleh dipakai sebagai password: NIS, nomor nasabah, dan nomor rekening.
     *
     * @return list<string>
     */
    public function identitasTerlarang(AkunNasabah $akun): array
    {
        $nasabah = $akun->nasabah;

        return array_values(array_filter([
            $nasabah->nis,
            $nasabah->nomor,
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

    /**
     * 8 karakter, selalu berisi huruf dan angka.
     */
    public function passwordAcak(int $panjang = 8): string
    {
        $semua = self::HURUF.self::ANGKA;
        $karakter = [self::HURUF[random_int(0, strlen(self::HURUF) - 1)], self::ANGKA[random_int(0, strlen(self::ANGKA) - 1)]];

        while (count($karakter) < $panjang) {
            $karakter[] = $semua[random_int(0, strlen($semua) - 1)];
        }

        return implode('', Arr::shuffle($karakter));
    }
}
