<?php

namespace App\Providers;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
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

        $this->logAuthEvents();
    }

    protected function logAuthEvents(): void
    {
        Event::listen(function (Login $event) {
            if ($event->user instanceof User) {
                $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
            }

            activity('auth')->causedBy($event->user)->event('login')
                ->withProperties(['ip' => request()->ip(), 'guard' => $event->guard])
                ->log('Login');
        });

        Event::listen(function (Logout $event) {
            if ($event->user) {
                activity('auth')->causedBy($event->user)->event('logout')
                    ->withProperties(['ip' => request()->ip(), 'guard' => $event->guard])
                    ->log('Logout');
            }
        });
    }
}
