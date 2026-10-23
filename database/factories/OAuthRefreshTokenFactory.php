<?php

namespace Database\Factories;

use App\Models\ConnectorGrant;
use App\Models\OAuthRefreshToken;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OAuthRefreshToken>
 */
class OAuthRefreshTokenFactory extends Factory
{
    public function definition(): array
    {
        return [
            'token_hash' => hash('sha256', Str::random(64)),
            'grant_id' => ConnectorGrant::factory(),
            'access_token_id' => null,
            'client_id' => fn (array $attributes): string => (string) ConnectorGrant::query()->whereKey($attributes['grant_id'])->value('client_id'),
            'user_id' => fn (array $attributes): int => (int) ConnectorGrant::query()->whereKey($attributes['grant_id'])->value('user_id'),
            'scopes' => fn (array $attributes): array => ConnectorGrant::query()->find($attributes['grant_id'])?->scopes ?? ['director'],
            'expires_at' => now()->addDays((int) config('agents.oauth.refresh_token_ttl_days', 30)),
        ];
    }

    /**
     * Stores sha256($plaintext) so a test can send the plaintext as the refresh token.
     */
    public function withPlaintext(string $plaintext): static
    {
        return $this->state(fn (): array => [
            'token_hash' => hash('sha256', $plaintext),
        ]);
    }
}
