<?php

namespace App\Providers;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Carbon::setLocale(config('app.locale'));

        Model::preventLazyLoading(! $this->app->isProduction());

        Password::defaults(fn () => Password::min(8));

        // Cadangan bila proxy hosting tidak mengirim X-Forwarded-Proto: ikuti skema APP_URL.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        $this->logAuthEvents();
        $this->rateLimitInternetBanking();
    }

    /**
     * Batas permintaan Internet Banking nasabah, per akun (atau IP bila belum login).
     */
    protected function rateLimitInternetBanking(): void
    {
        $kunci = fn (Request $request) => Auth::guard('nasabah')->id() ?? $request->ip();

        RateLimiter::for('ib', fn (Request $request) => Limit::perMinute(60)->by('ib|'.$kunci($request)));
        RateLimiter::for('ib-password', fn (Request $request) => Limit::perMinute(10)->by('ib-password|'.$kunci($request)));
        RateLimiter::for('ib-pdf', fn (Request $request) => Limit::perMinute(10)->by('ib-pdf|'.$kunci($request)));
    }

    protected function logAuthEvents(): void
    {
        Event::listen(function (Login $event) {
            // Login nasabah Internet Banking dicatat sendiri di log `nasabah`.
            if ($event->guard === 'nasabah') {
                return;
            }

            if ($event->user instanceof User) {
                $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
            }

            activity('auth')->causedBy($event->user)->event('login')
                ->withProperties(['ip' => request()->ip(), 'guard' => $event->guard])
                ->log('Login');
        });

        Event::listen(function (Logout $event) {
            if ($event->user && $event->guard !== 'nasabah') {
                activity('auth')->causedBy($event->user)->event('logout')
                    ->withProperties(['ip' => request()->ip(), 'guard' => $event->guard])
                    ->log('Logout');
            }
        });
    }
}
