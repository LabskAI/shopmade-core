<?php

namespace App\Core\Mcp\Tools;

use App\Core\Mcp\McpContext;
use App\Core\Packages\Hooks;
use Illuminate\Contracts\Container\Container;

/**
 * The connector's tools by name: core's site tools plus any a package registers with Hooks::mcpTool(). Tools are
 * resolved from the container on use so their services can be swapped (and mocked) per request.
 */
class ToolRegistry
{
    /**
     * @var array<string, class-string<Tool>>
     */
    public const Tools = [
        'facts_get' => FactsGetTool::class,
        'facts_save' => FactsSaveTool::class,
        'pages_list' => PagesListTool::class,
        'page_get' => PageGetTool::class,
        'page_create' => PageCreateTool::class,
        'page_save' => PageSaveTool::class,
        'page_delete' => PageDeleteTool::class,
        'page_history' => PageHistoryTool::class,
        'page_restore' => PageRestoreTool::class,
        'theme_get' => ThemeGetTool::class,
        'theme_save' => ThemeSaveTool::class,
        'site_lint' => SiteLintTool::class,
    ];

    public function __construct(private Container $container, private Hooks $hooks) {}

    /**
     * @return array<string, class-string<Tool>>
     */
    public function all(): array
    {
        return self::Tools + $this->hooks->mcpTools();
    }

    public function find(string $name): ?Tool
    {
        $class = $this->all()[$name] ?? null;

        return $class === null ? null : $this->container->make($class);
    }

    /**
     * The tools this token may call.
     *
     * @return list<Tool>
     */
    public function visibleTo(McpContext $context): array
    {
        $tools = [];

        foreach ($this->all() as $class) {
            $tool = $this->container->make($class);

            if ($tool->allows($context)) {
                $tools[] = $tool;
            }
        }

        return $tools;
    }
}
