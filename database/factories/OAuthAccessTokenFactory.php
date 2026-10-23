<?php

namespace Database\Factories;

use App\Models\ConnectorGrant;
use App\Models\OAuthAccessToken;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OAuthAccessToken>
 */
class OAuthAccessTokenFactory extends Factory
{
    public function definition(): array
    {
        return [
            'token_hash' => hash('sha256', Str::random(64)),
            'grant_id' => ConnectorGrant::factory(),
            'client_id' => fn (array $attributes): string => (string) ConnectorGrant::query()->whereKey($attributes['grant_id'])->value('client_id'),
            'user_id' => fn (array $attributes): int => (int) ConnectorGrant::query()->whereKey($attributes['grant_id'])->value('user_id'),
            'scopes' => fn (array $attributes): array => ConnectorGrant::query()->find($attributes['grant_id'])?->scopes ?? ['director'],
            'resource' => null,
            'expires_at' => now()->addMinutes((int) config('agents.oauth.access_token_ttl_minutes', 60)),
        ];
    }

    /**
     * Stores sha256($plaintext) so a test can send the plaintext as the Bearer token.
     */
    public function withPlaintext(string $plaintext): static
    {
        return $this->state(fn (): array => [
            'token_hash' => hash('sha256', $plaintext),
        ]);
    }

    /**
     * Creates a token and returns it with the plaintext Bearer value.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{0: OAuthAccessToken, 1: string}
     */
    public static function createWithPlaintext(array $attributes = []): array
    {
        $plaintext = Str::random(64);

        return [OAuthAccessToken::factory()->withPlaintext($plaintext)->create($attributes), $plaintext];
    }
}
