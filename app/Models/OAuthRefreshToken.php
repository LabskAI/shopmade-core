<?php

namespace App\Models;

use Database\Factories\OAuthRefreshTokenFactory;
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
    'access_token_id',
    'client_id',
    'user_id',
    'scopes',
    'expires_at',
    'used_at',
    'revoked_at',
])]
#[Hidden(['token_hash'])]
class OAuthRefreshToken extends Model
{
    /** @use HasFactory<OAuthRefreshTokenFactory> */
    use HasFactory, Prunable;

    protected $table = 'oauth_refresh_tokens';

    protected function casts(): array
    {
        return [
            'access_token_id' => 'integer',
            'scopes' => 'array',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function grant(): BelongsTo
    {
        return $this->belongsTo(ConnectorGrant::class, 'grant_id');
    }

    public function accessToken(): BelongsTo
    {
        return $this->belongsTo(OAuthAccessToken::class, 'access_token_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(OAuthClient::class, 'client_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Used and revoked refresh tokens are kept until they expire so a replay is
     * still recognised (reuse detection revokes the whole grant).
     */
    public function prunable(): Builder
    {
        return static::query()->where('expires_at', '<', now()->subDays(7));
    }
}
