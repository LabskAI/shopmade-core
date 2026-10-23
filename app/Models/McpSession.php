<?php

namespace App\Models;

use Database\Factories\McpSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'id',
    'grant_id',
    'user_id',
    'client_id',
    'protocol_version',
    'client_info',
    'last_seen_at',
    'ended_at',
])]
class McpSession extends Model
{
    /** @use HasFactory<McpSessionFactory> */
    use HasFactory, Prunable;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'client_info' => 'array',
            'last_seen_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function grant(): BelongsTo
    {
        return $this->belongsTo(ConnectorGrant::class, 'grant_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function prunable(): Builder
    {
        $idleCutoff = now()->subMinutes((int) config('agents.mcp.session_ttl_minutes', 1440))->subDays(7);

        return static::query()
            ->where('ended_at', '<', now()->subDays(7))
            ->orWhere('last_seen_at', '<', $idleCutoff);
    }
}
