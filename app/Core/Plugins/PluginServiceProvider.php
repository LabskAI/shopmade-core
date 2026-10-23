<?php

namespace App\Core\Plugins;

use App\Enums\PluginStatus;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Throwable;

class PluginServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Registry::class);

        $registry = $this->app->make(Registry::class);
        $statusMap = $registry->statusMap();
        $loader = require base_path('vendor/autoload.php');

        foreach ($registry->discover() as $manifest) {
            $status = $statusMap[$manifest->slug] ?? PluginStatus::Active->value;

            if ($status !== PluginStatus::Active->value) {
                continue;
            }

            try {
                $loader->addPsr4('Plugins\\'.Str::studly($manifest->slug).'\\', $manifest->directory().'/src/');
                $this->app->register($manifest->provider);
            } catch (Throwable $exception) {
                Log::error('Failed to register plugin.', [
                    'slug' => $manifest->slug,
                    'message' => $exception->getMessage(),
                ]);
            }
        }
    }

    public function boot(): void
    {
        Blade::directive('pluginAttachments', function (string $expression): string {
            return "<?php echo app(\\App\\Core\\Plugins\\Registry::class)->renderAttachments({$expression}); ?>";
        });
    }
}
