<?php

namespace Database\Factories;

use App\Enums\PluginSource;
use App\Enums\PluginStatus;
use App\Models\Plugin;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plugin>
 */
class PluginFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);
        $slug = Str::slug($name);

        return [
            'slug' => $slug,
            'name' => Str::title($name),
            'version' => '1.0.0',
            'source' => PluginSource::Local,
            'status' => PluginStatus::Active,
            'manifest' => [
                'slug' => $slug,
                'name' => Str::title($name),
                'version' => '1.0.0',
                'nav' => [],
                'attach' => null,
                'tables' => [],
                'depends' => [],
            ],
            'tables' => [],
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['status' => PluginStatus::Disabled]);
    }
}
