<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        \Illuminate\Http\Middleware\TrustProxies::at(config('operations.trusted_proxies', []));
        Password::defaults(fn () => Password::min(12)->mixedCase()->numbers()->symbols());
        Gate::define('operate', fn (User $user) => $user->is_active && in_array($user->role, ['admin', 'staff'], true));
        Gate::define('administer', fn (User $user) => $user->is_active && $user->role === 'admin');
        RateLimiter::for('sms', fn (Request $request) => [Limit::perMinute(2)->by('sms:'.$request->user()->id), Limit::perHour(20)->by('sms-hour:'.$request->user()->id)]);
        RateLimiter::for('exports', fn (Request $request) => Limit::perMinute(2)->by('exports:'.$request->user()->id));
        RateLimiter::for('directory-reads', fn (Request $request) => Limit::perMinute(60)->by('directory:'.$request->user()->id));
        RateLimiter::for('imports', fn (Request $request) => Limit::perMinute(3)->by('import:'.$request->user()->id));
        RateLimiter::for('mutations', fn (Request $request) => Limit::perMinute(60)->by('write:'.$request->user()->id));
        if ($this->app->isProduction()) {
            config(['app.debug' => false, 'session.secure' => true, 'session.encrypt' => true]);
        }
    }
}
