<?php

namespace App\Models;

use Database\Factories\OAuthAccessTokenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'token_hash',
    'grant_id',
    'client_id',
    'user_id',
    'scopes',
    'resource',
    'expires_at',
    'revoked_at',
    'last_used_at',
])]
#[Hidden(['token_hash'])]
class OAuthAccessToken extends Model
{
    /** @use HasFactory<OAuthAccessTokenFactory> */
    use HasFactory, Prunable;

    protected $table = 'oauth_access_tokens';

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function grant(): BelongsTo
    {
        return $this->belongsTo(ConnectorGrant::class, 'grant_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(OAuthClient::class, 'client_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function prunable(): Builder
    {
        return static::query()
            ->where('expires_at', '<', now()->subDays(7))
            ->orWhere('revoked_at', '<', now()->subDays(7));
    }
}
