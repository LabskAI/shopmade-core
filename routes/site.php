<?php

use App\Core\Front\FrontKit;
use App\Http\Controllers\Admin\ConnectorController;
use App\Http\Controllers\Site\DiscoveryCheckController;
use App\Http\Controllers\Site\DiscoveryController;
use App\Http\Controllers\Site\FactsController;
use App\Http\Controllers\Site\PageController;
use App\Http\Controllers\Site\PageHistoryController;
use App\Http\Controllers\Site\ThemeController;
use Illuminate\Support\Facades\Route;

/*
| The website editor (Admin → Website): pages, facts, theme and discovery of the company front
| in fronts/{agents.company}. Every save writes the live file and keeps a revision.
*/

Route::middleware(['auth', 'can:site.edit'])
    ->prefix('admin/site')
    ->name('site.')
    ->where(['lang' => FrontKit::LANGUAGE_PATTERN, 'slug' => FrontKit::SLUG_PATTERN])
    ->group(function () {
        Route::redirect('/', '/admin/site/pages')->name('index');
        Route::get('pages', [PageController::class, 'index'])->name('pages.index');
        Route::get('pages/create', [PageController::class, 'create'])->name('pages.create');
        Route::post('pages', [PageController::class, 'store'])->name('pages.store');
        Route::get('pages/{lang}/{slug}/edit', [PageController::class, 'edit'])->name('pages.edit');
        Route::put('pages/{lang}/{slug}', [PageController::class, 'update'])->name('pages.update');
        Route::delete('pages/{lang}/{slug}', [PageController::class, 'destroy'])->name('pages.destroy');
        Route::get('pages/{lang}/{slug}/history', [PageHistoryController::class, 'index'])->name('pages.history');
        Route::post('pages/{lang}/{slug}/restore', [PageHistoryController::class, 'store'])->name('pages.restore');
        Route::get('facts', [FactsController::class, 'edit'])->name('facts.edit');
        Route::put('facts', [FactsController::class, 'update'])->name('facts.update');
        Route::get('theme', [ThemeController::class, 'edit'])->name('theme.edit');
        Route::put('theme', [ThemeController::class, 'update'])->name('theme.update');
        Route::view('forms', 'site.forms.show')->name('forms.show');
        Route::get('discovery', [DiscoveryController::class, 'show'])->name('discovery.show');
        Route::put('discovery', [DiscoveryController::class, 'update'])->name('discovery.update');
        Route::post('discovery/check', [DiscoveryCheckController::class, 'store'])->name('discovery.check');
    });

Route::middleware(['auth', 'can:connectors.manage'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('connectors', [ConnectorController::class, 'index'])->name('connectors.index');
    Route::put('connectors/{grant}', [ConnectorController::class, 'update'])->name('connectors.update');
    Route::delete('connectors/{grant}', [ConnectorController::class, 'destroy'])->name('connectors.destroy');
});
