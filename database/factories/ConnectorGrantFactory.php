<?php

namespace Database\Factories;

use App\Models\ConnectorGrant;
use App\Models\OAuthClient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConnectorGrant>
 */
class ConnectorGrantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->superAdmin(),
            'client_id' => OAuthClient::factory(),
            'scopes' => ['director'],
            'last_seen_at' => null,
            'revoked_at' => null,
        ];
    }
}
