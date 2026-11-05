<?php

namespace App\Providers;

use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Super Admin can do everything; return null (not false) so other users fall through to policies.
        Gate::before(fn (User $user) => $user->hasRole(User::ROLE_SUPER_ADMIN) ? true : null);

        // Catch N+1 queries during development and in tests; production just carries on.
        Model::preventLazyLoading(! app()->isProduction());

        Password::defaults(fn () => app()->isProduction()
            ? Password::min(10)->letters()->mixedCase()->numbers()->uncompromised()
            : Password::min(8));

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('public', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
        // Named limiters keep separate counters (plain throttle:x,y share one counter per user).
        RateLimiter::for('previews', fn (Request $request) => Limit::perMinute(600)->by('previews:'.($request->user()?->id ?: $request->ip())));
        RateLimiter::for('saves', fn (Request $request) => Limit::perMinute(60)->by('saves:'.($request->user()?->id ?: $request->ip())));
        RateLimiter::for('beacons', fn (Request $request) => Limit::perMinute(60)->by('beacons:'.$request->ip()));
        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(30)->by('uploads:'.($request->user()?->id ?: $request->ip())));

        Event::listen(Login::class, function (Login $event) {
            if ($event->user instanceof User) {
                $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
                app(ActivityLogger::class)->log('auth.login', $event->user, 'Signed in', userId: $event->user->id);
            }
        });

        Event::listen(Logout::class, function (Logout $event) {
            if ($event->user instanceof User) {
                app(ActivityLogger::class)->log('auth.logout', $event->user, 'Signed out', userId: $event->user->id);
            }
        });
    }
}
