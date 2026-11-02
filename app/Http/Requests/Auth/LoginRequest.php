<?php

namespace App\Http\Requests\Auth;

use App\Http\Modules\Perangkat\Services\PerangkatService;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public const MAX_ATTEMPTS = 5;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['username' => 'nama pengguna', 'password' => 'kata sandi'];
    }

    /**
     * Memvalidasi kredensial dan mengembalikan user; rate limit per username + IP.
     *
     * @throws ValidationException
     */
    public function validateCredentials(): User
    {
        $this->ensureIsNotRateLimited();

        /** @var User|null $user */
        $user = User::where('username', $this->string('username'))->first();

        if (! $user || ! Hash::check($this->string('password'), $user->password)) {
            RateLimiter::hit($this->throttleKey(), 60);

            throw ValidationException::withMessages([
                'username' => __('auth.failed'),
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'username' => 'Akun Anda dinonaktifkan. Hubungi bendahara.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        return $user;
    }

    public function authenticate(): void
    {
        $user = $this->validateCredentials();

        $this->pastikanPerangkat($user);

        Auth::login($user, $this->boolean('remember'));
    }

    /**
     * Teller hanya boleh login dari komputer yang didaftarkan bendahara.
     *
     * @throws ValidationException
     */
    public function pastikanPerangkat(User $user): void
    {
        $perangkat = app(PerangkatService::class);

        if ($perangkat->diizinkan($user, $this)) {
            return;
        }

        $perangkat->catatDitolak($user, $this);

        throw ValidationException::withMessages(['username' => PerangkatService::PESAN_DITOLAK]);
    }

    /**
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'username' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('username')).'|'.$this->ip());
    }
}
