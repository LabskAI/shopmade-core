<?php

namespace App\Providers;

use App\Core\Packages\Hooks;
use App\Core\Plugins\Registry;
use App\Models\User;
use App\Support\Capabilities;
use App\Support\CoreTools;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Hooks::class);
    }

    public function boot(): void
    {
        Paginator::defaultView('pagination.shopmade');

        RateLimiter::for('login', fn (Request $request): array => [
            Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(20)->by((string) $request->ip()),
        ]);

        Gate::before(fn (User $user): ?bool => $user->isDeactivated() ? false : null);

        foreach (array_keys(Capabilities::map()) as $capability) {
            Gate::define($capability, fn (User $user): bool => Capabilities::allows($user, $capability));
        }

        Gate::define('manage-plugins', fn (User $user): bool => $user->isSuperAdmin() || $user->isAdmin());
        Gate::define('manage-site', fn (User $user): bool => $user->isSuperAdmin() || $user->isAdmin());

        View::composer(['layouts.app', 'layouts.marketing'], function ($view): void {
            $user = auth()->user();
            $isApp = $view->name() === 'layouts.app';

            try {
                $plugins = ($isApp && $user) ? app(Registry::class)->navFor($user) : [];
            } catch (\Throwable) {
                $plugins = [];
            }

            $view->with([
                'navPlugins' => $plugins,
                'navCoreGroups' => ($isApp && $user) ? CoreTools::navGroups($user) : [],
            ]);
        });
    }
}
