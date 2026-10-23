<?php

namespace App\Models;

use Database\Factories\OAuthClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'id',
    'name',
    'redirect_uris',
    'grant_types',
    'token_endpoint_auth_method',
    'scope',
    'client_uri',
    'logo_uri',
    'registration_meta',
    'revoked_at',
])]
class OAuthClient extends Model
{
    /** @use HasFactory<OAuthClientFactory> */
    use HasFactory;

    public $incrementing = false;

    protected $table = 'oauth_clients';

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'redirect_uris' => 'array',
            'grant_types' => 'array',
            'registration_meta' => 'array',
            'revoked_at' => 'datetime',
        ];
    }

    public function grants(): HasMany
    {
        return $this->hasMany(ConnectorGrant::class, 'client_id');
    }
}
