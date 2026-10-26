<?php

namespace App\Core\Mcp;

use App\Core\Agents\AgentRefusedException;
use App\Core\Mcp\Tools\Tool;
use App\Core\Mcp\Tools\ToolInvoker;
use App\Core\Mcp\Tools\ToolRegistry;
use App\Enums\ConnectorScope;
use App\Models\McpSession;
use stdClass;
use Throwable;

/**
 * The JSON-RPC 2.0 method dispatcher of the Shopmade MCP server. Transport concerns (HTTP status,
 * sessions, batches) live in McpController; this class answers one message at a time.
 */
class McpServer
{
    /**
     * Requests this class does not count against the per-token limit: initialize and ping never count, and
     * ToolInvoker counts tools/call itself so that a rate-limited call is audited like any other refusal (a
     * tools/call whose params never reach it is counted here instead, see toolCallParams()).
     *
     * @var list<string>
     */
    private const NotCountedHere = ['initialize', 'ping', 'tools/call'];

    public function __construct(
        private ToolRegistry $tools,
        private ToolInvoker $invoker,
        private McpResources $resources,
        private McpPrompts $prompts,
        private McpSessions $sessions,
        private ProtocolVersion $versions,
        private McpRateLimit $rateLimit,
    ) {}

    /**
     * Negotiates the protocol version and opens a session.
     *
     * @param  array<string, mixed>  $message
     * @return array{0: array<string, mixed>, 1: ?McpSession} the JSON-RPC response and the new session (null on error)
     */
    public function initialize(array $message, McpContext $context): array
    {
        $params = $message['params'] ?? [];

        if (! is_array($params) || ! is_string($params['protocolVersion'] ?? null)) {
            return [$this->error($message['id'], McpError::invalidParams('initialize needs params.protocolVersion.', 'Send the latest protocol version your client supports.')), null];
        }

        $version = $this->versions->negotiate($params['protocolVersion']);
        $clientInfo = is_array($params['clientInfo'] ?? null) ? $params['clientInfo'] : [];
        $session = $this->sessions->start($context, $version, $clientInfo);
        $company = (string) config('agents.company');

        return [$this->result($message['id'], [
            'protocolVersion' => $version,
            'capabilities' => [
                'tools' => ['listChanged' => false],
                'resources' => new stdClass,
                'prompts' => new stdClass,
            ],
            'serverInfo' => [
                'name' => (string) config('agents.mcp.server_name', 'shopmade-agents'),
                'title' => "Shopmade ({$company})",
                'version' => $this->serverVersion(),
            ],
            'instructions' => $this->instructions($company),
        ]), $session];
    }

    /**
     * Answers one JSON-RPC request (anything but initialize). Every request except ping counts against the
     * caller's per-token limit, batched or not.
     *
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>
     */
    public function request(array $message, McpContext $context, string $protocolVersion): array
    {
        $method = (string) $message['method'];

        try {
            if (! in_array($method, self::NotCountedHere, true)) {
                $this->rateLimit->hit($context);
            }

            $params = $method === 'tools/call' ? $this->toolCallParams($message, $context) : $this->params($message);

            return $this->result($message['id'], match ($method) {
                'ping' => new stdClass,
                'initialize' => throw McpError::invalidRequest('This session is already initialized.', 'Send initialize without an Mcp-Session-Id header to start a new session.'),
                'tools/list' => ['tools' => array_map(fn (Tool $tool): array => $tool->definition(), $this->tools->visibleTo($context))],
                'tools/call' => $this->invoker->call($params['name'] ?? null, $params['arguments'] ?? [], $context, $protocolVersion),
                'resources/list' => ['resources' => $this->readable($context, fn (): array => $this->resources->list())],
                'resources/templates/list' => ['resourceTemplates' => $this->readable($context, fn (): array => $this->resources->templates())],
                'resources/read' => ['contents' => $this->readable($context, fn (): array => $this->resources->read($this->uri($params)))],
                'prompts/list' => ['prompts' => $this->readable($context, fn (): array => $this->prompts->list())],
                'prompts/get' => $this->readable($context, fn (): array => $this->prompts->get($this->promptName($params), $this->promptArguments($params))),
                default => throw McpError::methodNotFound($method),
            });
        } catch (McpError $error) {
            return $this->error($message['id'], $error);
        } catch (AgentRefusedException $refusal) {
            return $this->error($message['id'], McpError::fromRefusal($refusal));
        } catch (Throwable $exception) {
            report($exception);

            return $this->error($message['id'], McpError::internal($method));
        }
    }

    /**
     * Notifications get no answer; initialized and cancellations need no action from a stateless server.
     */
    public function notify(McpSession $session): void
    {
        $this->sessions->touch($session);
    }

    /**
     * @return array<string, mixed>
     */
    public function result(int|string $id, mixed $result): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    }

    /**
     * @return array<string, mixed>
     */
    public function error(int|string|null $id, McpError $error): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => $error->toError()];
    }

    /**
     * Resources and prompts expose company data: they need the director scope and agents access.
     *
     * @template TValue
     *
     * @param  callable(): TValue  $read
     * @return TValue
     */
    private function readable(McpContext $context, callable $read): mixed
    {
        if (! $context->hasScope(ConnectorScope::Operator) && ! $context->hasScope(ConnectorScope::Director)) {
            throw McpError::forbidden(
                "Resources and prompts need the operator or director scope; this token has: {$context->scopeList()}.",
                'Reconnect the connector and consent to a scope.',
            );
        }

        if ($context->user->cannot('site.edit')) {
            throw McpError::forbidden("{$context->user->name} can no longer edit the website.", 'Ask an admin to restore the role.');
        }

        return $read();
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>
     */
    private function params(array $message): array
    {
        $params = $message['params'] ?? [];

        if (! is_array($params) || ($params !== [] && array_is_list($params))) {
            throw McpError::invalidParams('params must be an object.', 'Send params as a JSON object.');
        }

        return $params;
    }

    /**
     * The params of a tools/call. ToolInvoker counts the call against the per-token limit, but a call whose params are
     * not an object never reaches it, so that one is counted here: malformed calls are throttled too, each once.
     *
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>
     *
     * @throws McpError invalid params, or -32003 once the token has used its budget
     */
    private function toolCallParams(array $message, McpContext $context): array
    {
        try {
            return $this->params($message);
        } catch (McpError $error) {
            $this->rateLimit->hit($context);

            throw $error;
        }
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function uri(array $params): string
    {
        $uri = $params['uri'] ?? null;

        if (! is_string($uri) || $uri === '' || strlen($uri) > 500) {
            throw McpError::invalidParams('params.uri is required.', 'Pass a URI from resources/list or a filled resource template.');
        }

        return $uri;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function promptName(array $params): string
    {
        $name = $params['name'] ?? null;

        if (! is_string($name) || $name === '') {
            throw McpError::invalidParams('params.name is required.', 'Call prompts/list for the prompt names.');
        }

        return $name;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function promptArguments(array $params): array
    {
        $arguments = $params['arguments'] ?? [];

        if (! is_array($arguments) || ($arguments !== [] && array_is_list($arguments))) {
            throw McpError::invalidParams('params.arguments must be an object.', 'Send prompt arguments as a JSON object of strings.');
        }

        return $arguments;
    }

    private function serverVersion(): string
    {
        return '2.0.0';
    }

    private function instructions(string $company): string
    {
        $perMinute = (int) config('agents.mcp.rate_limit_per_minute', 60);

        return "Shopmade site tools for {$company}. You edit the company website directly: every save is live at once and kept as a "
            .'revision (page_history lists them, page_restore brings one back). Read first: the skill resources (shopmade://'
            .$company.'/skills/…) for the house voice and design rules, facts_get for the business facts (never write anything listed in '
            .'never_write), pages_list and page_get for the pages, theme_get for the design tokens, components_css_get for the component '
            .'styles. Edit with page_create, page_save (bodies may use the kit components: :::section, :::cards, :::steps, :::flow, :::faq, '
            .':::cta, :::form…), page_delete, facts_save, theme_save, components_css_save and asset_put; each write returns the lint report '
            .'of the front, so fix any error it lists. '
            ."Limits: {$perMinute} requests per minute (tool calls, resources and prompts together).";
    }
}
