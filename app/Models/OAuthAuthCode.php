<?php

namespace App\Models;

use Database\Factories\OAuthAuthCodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'id',
    'client_id',
    'user_id',
    'scopes',
    'redirect_uri',
    'code_challenge',
    'code_challenge_method',
    'resource',
    'expires_at',
    'consumed_at',
])]
class OAuthAuthCode extends Model
{
    /** @use HasFactory<OAuthAuthCodeFactory> */
    use HasFactory, Prunable;

    public const UPDATED_AT = null;

    public $incrementing = false;

    protected $table = 'oauth_auth_codes';

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
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
     * Codes live ten minutes and are single use; keep a day for audits.
     */
    public function prunable(): Builder
    {
        return static::query()->where('expires_at', '<', now()->subDay());
    }
}
