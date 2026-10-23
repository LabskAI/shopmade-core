<?php

namespace Database\Factories;

use App\Models\OAuthAuthCode;
use App\Models\OAuthClient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OAuthAuthCode>
 */
class OAuthAuthCodeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => hash('sha256', Str::random(64)),
            'client_id' => OAuthClient::factory(),
            'user_id' => User::factory()->superAdmin(),
            'scopes' => ['director'],
            'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', Str::random(64), true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
            'resource' => null,
            'expires_at' => now()->addMinutes((int) config('agents.oauth.auth_code_ttl_minutes', 10)),
        ];
    }
}
