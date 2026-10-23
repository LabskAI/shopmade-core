<?php

namespace App\Core\Mcp\Tools;

/**
 * What a tool returns: structured data (sent as JSON text and as structuredContent) plus optional
 * images such as a preview screenshot.
 */
final readonly class ToolResult
{
    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{data: string, mimeType: string}>  $images  base64 data
     */
    public function __construct(
        public array $data,
        public array $images = [],
    ) {}
}
