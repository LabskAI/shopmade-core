<?php

namespace App\Core\Plugins;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use ReflectionClass;
use RuntimeException;

abstract class Plugin extends ServiceProvider
{
    public function boot(): void
    {
        $manifest = $this->manifest();

        $this->loadMigrationsFrom($this->path('database/migrations'));
        $this->loadViewsFrom($this->path('resources/views'), $manifest->slug);

        $prefix = ltrim($manifest->mount, '/');

        Route::middleware($manifest->public ? ['web', "plugin:{$manifest->slug}"] : ['web', 'auth', 'can:staff.access', "plugin:{$manifest->slug}"])
            ->prefix($prefix)
            ->name("{$manifest->slug}.")
            ->group($this->path('routes/web.php'));
    }

    public function manifest(): Manifest
    {
        $manifest = Manifest::fromFile($this->path('plugin.json'));

        if ($manifest === null) {
            throw new RuntimeException('Invalid plugin.json at '.$this->path('plugin.json'));
        }

        return $manifest;
    }

    protected function path(string $relative = ''): string
    {
        $root = dirname((new ReflectionClass(static::class))->getFileName(), 2);

        return $relative === '' ? $root : $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }
}
