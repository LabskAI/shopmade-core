<?php

namespace App\Core\Mcp;

use App\Models\McpSession;
use Illuminate\Support\Str;

/**
 * Streamable HTTP sessions (mcp_sessions). A session belongs to the grant that initialized it, ends on
 * DELETE and expires after agents.mcp.session_ttl_minutes without traffic.
 */
class McpSessions
{
    private const TouchEverySeconds = 60;

    private const MaxClientInfoChars = 120;

    /**
     * @param  array<string, mixed>  $clientInfo
     */
    public function start(McpContext $context, string $protocolVersion, array $clientInfo): McpSession
    {
        return McpSession::query()->create([
            'id' => bin2hex(random_bytes(32)),
            'grant_id' => $context->grant->id,
            'user_id' => $context->user->id,
            'client_id' => Str::limit($context->clientId, 64, ''),
            'protocol_version' => $protocolVersion,
            'client_info' => $this->clientInfo($clientInfo),
            'last_seen_at' => now(),
        ]);
    }

    /**
     * The live session with this id for the caller's grant, or null (unknown, ended, expired or another grant's).
     */
    public function find(string $id, McpContext $context): ?McpSession
    {
        if ($id === '' || strlen($id) > 64) {
            return null;
        }

        $session = McpSession::query()->find($id);

        if ($session === null || $session->ended_at !== null || (int) $session->grant_id !== (int) $context->grant->id) {
            return null;
        }

        $lastSeen = $session->last_seen_at ?? $session->created_at;

        if ($lastSeen === null || $lastSeen->lt(now()->subMinutes($this->ttlMinutes()))) {
            return null;
        }

        return $session;
    }

    public function touch(McpSession $session): void
    {
        if ($session->last_seen_at !== null && $session->last_seen_at->gt(now()->subSeconds(self::TouchEverySeconds))) {
            return;
        }

        $session->forceFill(['last_seen_at' => now()])->save();
    }

    public function end(McpSession $session): void
    {
        $session->forceFill(['ended_at' => now()])->save();
    }

    private function ttlMinutes(): int
    {
        return max(1, (int) config('agents.mcp.session_ttl_minutes', 1440));
    }

    /**
     * @param  array<string, mixed>  $clientInfo
     * @return array<string, string>|null
     */
    private function clientInfo(array $clientInfo): ?array
    {
        $kept = [];

        foreach (['name', 'title', 'version'] as $key) {
            if (isset($clientInfo[$key]) && is_scalar($clientInfo[$key])) {
                $kept[$key] = Str::limit((string) $clientInfo[$key], self::MaxClientInfoChars, '');
            }
        }

        return $kept === [] ? null : $kept;
    }
}
