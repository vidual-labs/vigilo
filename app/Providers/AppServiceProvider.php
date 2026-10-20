<?php

namespace App\Providers;

use App\Domain\AdPlatforms\Http\ResilientHttp;
use App\Domain\Checks\CheckRegistry;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Each adapter gets its own instance so attempt counters stay per fetch.
        $this->app->bind(ResilientHttp::class, fn () => ResilientHttp::fromConfig());
        $this->app->singleton(CheckRegistry::class);
    }

    public function boot(): void
    {
        // Operators change things; viewers only look.
        Gate::define('operate', fn (User $user) => $user->is_active && $user->isOperator());

        // Generate https links when the install is served over https. Keyed on
        // APP_URL (not APP_ENV) so plain-HTTP installs keep working.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Reset links always point at APP_URL, never at the request's Host header.
        ResetPassword::createUrlUsing(fn (User $user, string $token) => rtrim((string) config('app.url'), '/')
            .'/reset-password/'.$token.'?email='.urlencode($user->email));

        $this->bootRateLimiters();

        // /up also proves the database answers, so the Docker healthcheck is honest.
        Event::listen(DiagnosingHealth::class, fn () => DB::select('select 1'));
    }

    private function bootRateLimiters(): void
    {
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(20)->by($request->ip()),
        ]);

        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(3)->by($request->ip()));
    }
}
