<?php

use App\Core\Front\FrontKit;
use App\Core\Front\FrontSite;
use App\Http\Controllers\Front\DiscoveryController;
use App\Http\Controllers\Front\PageController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/*
| The company's public front (fronts/{agents.company}), served under /{agents.front.prefix}
| on every host and, when agents.front.host is set, at the root of that host. Loaded before
| routes/web.php so the front host's root belongs to the front. Public pages are stateless:
| no session and no CSRF cookie (nothing on the front posts yet), so crawlers write nothing.
*/

$stateless = [StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class];

$frontRoutes = function (): void {
    Route::get('robots.txt', [DiscoveryController::class, 'robots'])->name('robots');
    Route::get('sitemap.xml', [DiscoveryController::class, 'sitemap'])->name('sitemap');
    Route::get('llms.txt', [DiscoveryController::class, 'llms'])->name('llms');
    Route::get('llms-full.txt', [DiscoveryController::class, 'llmsFull'])->name('llms-full');

    Route::get('/', [PageController::class, 'home'])->name('home');
    Route::get('{lang}', [PageController::class, 'language'])
        ->where('lang', FrontKit::LANGUAGE_PATTERN)
        ->name('language');
    Route::get('{lang}/{slug}', [PageController::class, 'localized'])
        ->where(['lang' => FrontKit::LANGUAGE_PATTERN, 'slug' => FrontKit::SLUG_PATTERN])
        ->name('localized');
    Route::get('{slug}', [PageController::class, 'show'])
        ->where('slug', FrontKit::SLUG_PATTERN)
        ->name('page');
};

if (FrontSite::prefix() !== '') {
    Route::prefix(FrontSite::prefix())->name('front.')->withoutMiddleware($stateless)->group($frontRoutes);
}

if (FrontSite::configuredHost() !== null) {
    Route::domain(FrontSite::configuredHost())->name('front.host.')->withoutMiddleware($stateless)->group($frontRoutes);
}
