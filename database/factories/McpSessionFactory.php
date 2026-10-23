<?php

namespace Database\Factories;

use App\Models\ConnectorGrant;
use App\Models\McpSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<McpSession>
 */
class McpSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => Str::random(48),
            'grant_id' => ConnectorGrant::factory(),
            'user_id' => fn (array $attributes): ?int => ConnectorGrant::query()->find($attributes['grant_id'])?->user_id,
            'client_id' => fn (array $attributes): ?string => ConnectorGrant::query()->find($attributes['grant_id'])?->client_id,
            'protocol_version' => '2025-06-18',
            'client_info' => ['name' => 'claude-ai', 'version' => '1.0.0'],
            'last_seen_at' => now(),
        ];
    }
}
