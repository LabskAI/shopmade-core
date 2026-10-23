<?php

namespace App\Core\Packages;

use App\Core\Mcp\Tools\Tool;
use App\Models\Customer;
use App\Models\User;
use Closure;
use InvalidArgumentException;

/**
 * Where installed Composer packages plug into core screens. A package's service provider registers its pieces
 * in boot(); core renders them and never names a package.
 *
 * - tool(): a staff nav entry (same shape as config/core-tools.php, checked on registration).
 * - mcpTool(): an MCP tool (a subclass of App\Core\Mcp\Tools\Tool) on the site's connector.
 * - status(): a package state for <x-status>, drawn like one of the core states.
 * - dashboardCard(): a view on the staff dashboard.
 * - customerPanel(): a view on the customer page, given the customer.
 * - portalTab() / portalSection(): a client portal tab, and a view on the portal home given the signed-in client.
 */
class Hooks
{
    /**
     * @var list<array{slug: string, label: string, group: string, route: string, pattern: string, view_roles: list<string>, manage_roles: list<string>}>
     */
    private array $tools = [];

    /**
     * @var array<string, class-string<Tool>>
     */
    private array $mcpTools = [];

    /**
     * @var array<string, array{like: string, label: string|null}>
     */
    private array $statuses = [];

    /**
     * @var list<array{view: string, data: Closure|null}>
     */
    private array $dashboardCards = [];

    /**
     * @var list<array{view: string, data: Closure|null}>
     */
    private array $customerPanels = [];

    /**
     * @var list<array{label: string, route: string, pattern: string, icon: string}>
     */
    private array $portalTabs = [];

    /**
     * @var list<array{view: string, data: Closure|null}>
     */
    private array $portalSections = [];

    /**
     * @param  array{slug: string, label: string, group: string, route: string, pattern: string, view_roles: list<string>, manage_roles: list<string>}  $tool
     */
    public function tool(array $tool): void
    {
        foreach (['slug', 'label', 'group', 'route', 'pattern'] as $key) {
            if (! is_string($tool[$key] ?? null) || $tool[$key] === '') {
                throw new InvalidArgumentException("A core tool needs a non-empty string '{$key}'.");
            }
        }

        foreach (['view_roles', 'manage_roles'] as $key) {
            if (! is_array($tool[$key] ?? null) || ! array_is_list($tool[$key]) || array_filter($tool[$key], fn (mixed $role): bool => ! is_string($role)) !== []) {
                throw new InvalidArgumentException("Core tool '{$tool['slug']}' needs '{$key}' as a list of role names.");
            }
        }

        $this->tools[] = $tool;
    }

    /**
     * @return list<array{slug: string, label: string, group: string, route: string, pattern: string, view_roles: list<string>, manage_roles: list<string>}>
     */
    public function tools(): array
    {
        return $this->tools;
    }

    /**
     * A core tool of the same name wins, so a package cannot replace the site tools.
     *
     * @param  class-string<Tool>  $class
     */
    public function mcpTool(string $name, string $class): void
    {
        $this->mcpTools[$name] = $class;
    }

    /**
     * @return array<string, class-string<Tool>>
     */
    public function mcpTools(): array
    {
        return $this->mcpTools;
    }

    /**
     * Shows $state in <x-status> with the colors of the core state $like (see components/status.blade.php).
     */
    public function status(string $state, string $like, ?string $label = null): void
    {
        $this->statuses[$state] = ['like' => $like, 'label' => $label];
    }

    /**
     * @return array<string, array{like: string, label: string|null}>
     */
    public function statuses(): array
    {
        return $this->statuses;
    }

    /**
     * @param  Closure(User): array<string, mixed>|null  $data
     */
    public function dashboardCard(string $view, ?Closure $data = null): void
    {
        $this->dashboardCards[] = ['view' => $view, 'data' => $data];
    }

    /**
     * @param  Closure(Customer): array<string, mixed>|null  $data
     */
    public function customerPanel(string $view, ?Closure $data = null): void
    {
        $this->customerPanels[] = ['view' => $view, 'data' => $data];
    }

    public function portalTab(string $label, string $route, ?string $pattern = null, string $icon = 'square'): void
    {
        $this->portalTabs[] = ['label' => $label, 'route' => $route, 'pattern' => $pattern ?? $route, 'icon' => $icon];
    }

    /**
     * @param  Closure(User): array<string, mixed>|null  $data
     */
    public function portalSection(string $view, ?Closure $data = null): void
    {
        $this->portalSections[] = ['view' => $view, 'data' => $data];
    }

    /**
     * @return list<array{view: string, data: array<string, mixed>}>
     */
    public function dashboardCards(User $user): array
    {
        return $this->resolve($this->dashboardCards, $user);
    }

    /**
     * @return list<array{view: string, data: array<string, mixed>}>
     */
    public function customerPanels(mixed $customer): array
    {
        return $this->resolve($this->customerPanels, $customer);
    }

    /**
     * Tabs whose route exists, so a package can be disabled without breaking the portal.
     *
     * @return list<array{label: string, route: string, pattern: string, icon: string}>
     */
    public function portalTabs(): array
    {
        return array_values(array_filter($this->portalTabs, fn (array $tab): bool => app('router')->has($tab['route'])));
    }

    /**
     * @return list<array{view: string, data: array<string, mixed>}>
     */
    public function portalSections(User $user): array
    {
        return $this->resolve($this->portalSections, $user);
    }

    /**
     * @param  list<array{view: string, data: Closure|null}>  $entries
     * @return list<array{view: string, data: array<string, mixed>}>
     */
    private function resolve(array $entries, mixed $subject): array
    {
        return array_map(fn (array $entry): array => [
            'view' => $entry['view'],
            'data' => $entry['data'] ? ($entry['data'])($subject) : [],
        ], $entries);
    }
}
