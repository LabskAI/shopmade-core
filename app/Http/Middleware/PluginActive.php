<?php

namespace App\Http\Middleware;

use App\Enums\PluginStatus;
use App\Models\Plugin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class PluginActive
{
    public function handle(Request $request, Closure $next, string $slug): Response
    {
        if (! Schema::hasTable('plugins')) {
            abort(404);
        }

        $plugin = Plugin::query()->where('slug', $slug)->first();

        if (! $plugin || $plugin->status !== PluginStatus::Active) {
            abort(404);
        }

        $request->attributes->set('plugin', $plugin);

        return $next($request);
    }
}
