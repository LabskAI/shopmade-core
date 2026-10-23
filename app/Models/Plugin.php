<?php

namespace App\Models;

use App\Core\Plugins\Registry;
use App\Enums\PluginSource;
use App\Enums\PluginStatus;
use Database\Factories\PluginFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

#[Fillable([
    'slug',
    'name',
    'version',
    'source',
    'status',
    'manifest',
    'tables',
])]
class Plugin extends Model
{
    /** @use HasFactory<PluginFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'source' => PluginSource::class,
            'status' => PluginStatus::class,
            'manifest' => 'array',
            'tables' => 'array',
        ];
    }

    protected static function booted(): void
    {
        $forget = function (): void {
            if (app()->bound('cache')) {
                Cache::forget(Registry::STATUS_CACHE_KEY);
            }
        };

        static::saved($forget);
        static::deleted($forget);
    }
}
