<?php

namespace App\Models;

use App\Enums\ConnectorScope;
use Database\Factories\ConnectorGrantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'client_id',
    'scopes',
    'last_seen_at',
    'revoked_at',
    'revoked_by',
])]
class ConnectorGrant extends Model
{
    /** @use HasFactory<ConnectorGrantFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(OAuthClient::class, 'client_id');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function accessTokens(): HasMany
    {
        return $this->hasMany(OAuthAccessToken::class, 'grant_id');
    }

    public function refreshTokens(): HasMany
    {
        return $this->hasMany(OAuthRefreshToken::class, 'grant_id');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }

    public function hasScope(ConnectorScope|string $scope): bool
    {
        $scope = $scope instanceof ConnectorScope ? $scope->value : $scope;

        return in_array($scope, $this->scopes ?? [], true);
    }
}
