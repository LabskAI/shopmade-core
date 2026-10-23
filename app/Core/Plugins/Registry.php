<?php

namespace App\Core\Plugins;

use App\Enums\PluginStatus;
use App\Models\Plugin;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class Registry
{
    public const STATUS_CACHE_KEY = 'plugins.status';

    /**
     * @return list<Manifest>
     */
    public function discover(): array
    {
        $manifests = [];

        foreach (glob(base_path('plugins/*/plugin.json')) ?: [] as $path) {
            $manifest = Manifest::fromFile($path);

            if ($manifest === null) {
                Log::warning('Skipping invalid plugin.json.', ['path' => $path]);

                continue;
            }

            $manifests[] = $manifest;
        }

        return $manifests;
    }

    /**
     * @return array<string, string>
     */
    public function statusMap(): array
    {
        $resolve = function (): array {
            if (! app()->bound('db')) {
                return [];
            }

            try {
                if (! Schema::hasTable('plugins')) {
                    return [];
                }

                return Plugin::query()
                    ->pluck('status', 'slug')
                    ->map(fn (mixed $status): string => $status instanceof PluginStatus ? $status->value : (string) $status)
                    ->all();
            } catch (\Throwable) {
                return [];
            }
        };

        if (! app()->bound('cache')) {
            return $resolve();
        }

        return Cache::remember(self::STATUS_CACHE_KEY, 60, $resolve);
    }

    /**
     * @return list<array{slug: string, label: string, route: string, pattern: string}>
     */
    public function navFor(?User $user): array
    {
        if (! $user || ! Schema::hasTable('plugins')) {
            return [];
        }

        $items = [];

        foreach (Plugin::query()->where('status', PluginStatus::Active)->orderBy('name')->get() as $plugin) {
            foreach ($plugin->manifest['nav'] ?? [] as $entry) {
                if (! is_array($entry) || ! is_string($entry['route'] ?? null) || ! is_string($entry['label'] ?? null)) {
                    continue;
                }

                $roles = $entry['roles'] ?? [];

                if (! in_array($user->role->value, $roles, true) && ! in_array('*', $roles, true)) {
                    continue;
                }

                if (! app('router')->has($entry['route'])) {
                    continue;
                }

                $items[] = [
                    'slug' => $plugin->slug,
                    'label' => $entry['label'],
                    'route' => $entry['route'],
                    'pattern' => $plugin->slug.'.*',
                ];
            }
        }

        return $items;
    }

    /**
     * @return list<array{label: string, url: string}>
     */
    public function attachmentButtons(string $attachTo, mixed $model): array
    {
        if (! Schema::hasTable('plugins')) {
            return [];
        }

        $buttons = [];

        foreach (Plugin::query()->where('status', PluginStatus::Active)->orderBy('name')->get() as $plugin) {
            $attach = $plugin->manifest['attach'] ?? null;

            if (! is_array($attach) || ($attach['plugin'] ?? null) !== $attachTo) {
                continue;
            }

            $route = $attach['route'] ?? null;
            $label = $attach['label'] ?? null;

            if (! is_string($route) || ! is_string($label) || ! app('router')->has($route)) {
                continue;
            }

            $buttons[] = [
                'label' => $label,
                'url' => route($route, $model),
            ];
        }

        return $buttons;
    }

    public function renderAttachments(string $attachTo, mixed $model): string
    {
        return view('components.plugin-attachments', [
            'buttons' => $this->attachmentButtons($attachTo, $model),
        ])->render();
    }
}
