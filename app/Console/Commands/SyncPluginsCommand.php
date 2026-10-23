<?php

namespace App\Console\Commands;

use App\Core\Plugins\Registry;
use App\Enums\PluginSource;
use App\Enums\PluginStatus;
use App\Models\Plugin;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

#[Signature('plugin:sync')]
#[Description('Upsert plugin registry rows from manifests on disk.')]
class SyncPluginsCommand extends Command
{
    public function handle(Registry $registry): int
    {
        $seen = [];

        foreach ($registry->discover() as $manifest) {
            $seen[] = $manifest->slug;

            $plugin = Plugin::query()->updateOrCreate(
                ['slug' => $manifest->slug],
                [
                    'name' => $manifest->name,
                    'version' => $manifest->version,
                    'source' => PluginSource::Local,
                    'manifest' => $manifest->raw,
                    'tables' => $manifest->tables,
                ],
            );

            if ($plugin->status === PluginStatus::Removed) {
                $plugin->update(['status' => PluginStatus::Active]);
            }
        }

        $vanished = Plugin::query();

        if ($seen !== []) {
            $vanished->whereNotIn('slug', $seen);
        }

        $vanished->update(['status' => PluginStatus::Removed]);

        Cache::forget(Registry::STATUS_CACHE_KEY);

        $this->info('Synced '.count($seen).' plugin(s).');

        return self::SUCCESS;
    }
}
