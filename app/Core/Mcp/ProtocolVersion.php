<?php

namespace App\Core\Mcp;

/**
 * MCP protocol revisions this server speaks (config agents.mcp.protocol_versions). Revisions are
 * ISO dates, so string order is release order.
 */
class ProtocolVersion
{
    /**
     * The revision that dropped JSON-RPC batching and added structuredContent and the MCP-Protocol-Version header.
     */
    public const StructuredContent = '2025-06-18';

    /**
     * Assumed when nothing else identifies the version (Streamable HTTP backwards compatibility).
     */
    public const Fallback = '2025-03-26';

    /**
     * @return list<string>
     */
    public function supported(): array
    {
        $versions = array_values(array_filter(
            (array) config('agents.mcp.protocol_versions', []),
            fn (mixed $version): bool => is_string($version) && $version !== '',
        ));

        rsort($versions);

        return $versions === [] ? [self::Fallback] : $versions;
    }

    public function latest(): string
    {
        return $this->supported()[0];
    }

    public function isSupported(string $version): bool
    {
        return in_array($version, $this->supported(), true);
    }

    /**
     * The client's version when we support it, otherwise our latest (the client may then disconnect).
     */
    public function negotiate(mixed $requested): string
    {
        return is_string($requested) && $this->isSupported($requested) ? $requested : $this->latest();
    }

    public function hasStructuredContent(string $version): bool
    {
        return strcmp($version, self::StructuredContent) >= 0;
    }

    public function allowsBatches(string $version): bool
    {
        return ! $this->hasStructuredContent($version);
    }
}
