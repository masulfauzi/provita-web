<?php

namespace App\Http\Modules\Perangkat\Services;

use App\Http\Modules\Perangkat\Models\Perangkat;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie as CookieResponse;

/**
 * Komputer terdaftar untuk teller. Browser komputer tersebut memegang token acak
 * di cookie (dienkripsi Laravel, httpOnly); database hanya menyimpan hash-nya.
 * Bendahara dan admin tidak dibatasi.
 */
class PerangkatService
{
    public const COOKIE = 'provita_perangkat';

    /** Peran yang boleh login dari komputer mana saja. */
    public const PERAN_BEBAS = ['bendahara', 'admin'];

    public const PESAN_DITOLAK = 'Komputer ini belum terdaftar untuk teller. Hubungi bendahara.';

    /** Menit; masa berlaku cookie ±5 tahun. */
    protected const UMUR_COOKIE = 60 * 24 * 365 * 5;

    /** Detik; jeda minimal pembaruan "terakhir dipakai" agar tidak menulis DB tiap request. */
    protected const JEDA_TANDAI = 300;

    /**
     * Mendaftarkan browser saat ini sebagai perangkat baru. Perangkat lama yang tokennya
     * dipegang browser ini dinonaktifkan karena tokennya diganti.
     *
     * @return array{0: Perangkat, 1: CookieResponse}
     */
    public function daftarkan(string $nama, User $oleh, ?Perangkat $lama = null): array
    {
        $token = Str::random(64);

        $perangkat = DB::transaction(function () use ($nama, $oleh, $lama, $token) {
            $lama?->update(['is_active' => false]);

            $perangkat = new Perangkat(['nama' => $nama]);
            $perangkat->forceFill(['token_hash' => $this->hash($token), 'didaftarkan_oleh' => $oleh->id])->save();

            return $perangkat;
        });

        return [$perangkat, Cookie::make(self::COOKIE, $token, self::UMUR_COOKIE)];
    }

    /**
     * Perangkat aktif milik browser yang mengirim request, atau null.
     */
    public function dariRequest(Request $request): ?Perangkat
    {
        $token = $request->cookie(self::COOKIE);

        if (! is_string($token) || $token === '') {
            return null;
        }

        return Perangkat::where('token_hash', $this->hash($token))->where('is_active', true)->first();
    }

    /**
     * Apakah user ini hanya boleh memakai komputer terdaftar.
     */
    public function wajibPerangkat(User $user): bool
    {
        return (bool) config('auth.perangkat.wajib')
            && $user->hasRole('teller')
            && ! $user->hasAnyRole(self::PERAN_BEBAS);
    }

    /**
     * Perangkat request ini sah untuk user tersebut (selalu true bila user tidak dibatasi).
     */
    public function diizinkan(User $user, Request $request): bool
    {
        if (! $this->wajibPerangkat($user)) {
            return true;
        }

        $perangkat = $this->dariRequest($request);

        if ($perangkat) {
            $this->tandaiDipakai($perangkat, $request);
        }

        return $perangkat !== null;
    }

    public function tandaiDipakai(Perangkat $perangkat, Request $request): void
    {
        if ($perangkat->terakhir_dipakai_at?->gt(now()->subSeconds(self::JEDA_TANDAI))) {
            return;
        }

        $perangkat->forceFill([
            'terakhir_dipakai_at' => now(),
            'ip_terakhir' => $request->ip(),
            'user_agent_terakhir' => Str::limit((string) $request->userAgent(), 250, ''),
        ])->saveQuietly();
    }

    public function catatDitolak(User $user, Request $request): void
    {
        activity('perangkat')->causedBy($user)->event('ditolak')
            ->withProperties(['ip' => $request->ip(), 'user_agent' => $request->userAgent()])
            ->log('Teller ditolak: komputer tidak terdaftar');
    }

    protected function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
