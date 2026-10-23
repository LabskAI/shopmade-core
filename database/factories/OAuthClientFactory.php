<?php

namespace Database\Factories;

use App\Models\OAuthClient;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OAuthClient>
 */
class OAuthClientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'name' => 'Claude',
            'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback'],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'token_endpoint_auth_method' => 'none',
            'scope' => null,
            'registration_meta' => null,
        ];
    }
}
