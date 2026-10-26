<?php

namespace App\Core\Mcp\Tools;

use App\Core\Agents\AgentRefusedException;
use App\Core\Front\FrontEditor;
use App\Core\Mcp\McpContext;

class AssetPutTool extends SiteTool
{
    public function name(): string
    {
        return 'asset_put';
    }

    public function title(): string
    {
        return 'Upload a site file';
    }

    public function description(): string
    {
        return 'Write a file under assets/ (images, woff2 fonts, mp4/webm video, icons, site.webmanifest; at most '
            .(FrontEditor::ASSET_MAX_BYTES / 1_000_000).' MB, no scripts) and commit it to the content history. '
            .'Pages then use it as assets/<path> (hero.image, image="assets/…", icon="asset:icons/x.svg", ![alt](assets/…)).';
    }

    public function inputSchema(): array
    {
        return $this->objectSchema([
            'path' => ['type' => 'string', 'maxLength' => 200, 'pattern' => '^(?:assets/)?[A-Za-z0-9][A-Za-z0-9_./-]*$', 'description' => 'Path under assets/, e.g. hero-art.svg or icons/robot.svg.'],
            'content_base64' => ['type' => 'string', 'minLength' => 1, 'maxLength' => (int) ceil(FrontEditor::ASSET_MAX_BYTES / 3) * 4 + 4, 'description' => 'The file, base64-encoded.'],
        ], ['path', 'content_base64']);
    }

    protected function writesSite(): bool
    {
        return true;
    }

    public function handle(array $arguments, McpContext $context): ToolResult
    {
        $content = base64_decode($arguments['content_base64'], true);

        if ($content === false) {
            throw new AgentRefusedException('content_base64 is not valid base64.', 'invalid_params');
        }

        $result = $this->editor->putAsset($arguments['path'], $content, $context->actor);

        return new ToolResult(array_filter([
            'saved' => $result['saved'],
            'path' => $result['path'],
            'url' => $result['url'],
            'reference' => 'assets/'.substr($result['path'], strrpos($result['path'], '/assets/') + strlen('/assets/')),
        ], fn (mixed $value): bool => $value !== null));
    }
}
