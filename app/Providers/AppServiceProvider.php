<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Registered;
use App\Listeners\SendWelcomePushNotification;

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
        Event::listen(
            Registered::class,
            SendWelcomePushNotification::class,
        );

        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(5)->by((string) $request->ip()));
        RateLimiter::for('claims', fn (Request $request) => Limit::perMinute(10)->by((string) $request->user()?->id));
        RateLimiter::for('users', fn (Request $request) => Limit::perMinute(10)->by((string) $request->user()?->id));
        RateLimiter::for('controls', fn (Request $request) => Limit::perMinute(30)->by((string) $request->user()?->id));
    }
}
