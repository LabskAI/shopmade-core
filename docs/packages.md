# Writing a Shopmade package

A feature (jobs, invoices, a shop, a CRM) is a Composer package with a Laravel service provider. Core loads it through Laravel package discovery and never references it by name. The package plugs into core screens and the MCP server through `App\Core\Packages\Hooks`, a singleton you resolve in your provider's `boot()`.

## Skeleton

```
shopmade-example/
  composer.json            "extra": {"laravel": {"providers": ["Shopmade\\Example\\ExampleServiceProvider"]}}
  src/ExampleServiceProvider.php
  src/Models/, src/Http/Controllers/, src/Mcp/
  database/migrations/
  resources/views/
  routes/web.php
  tests/
```

## Service provider

```php
namespace Shopmade\Example;

use App\Core\Packages\Hooks;
use App\Models\Customer;
use Illuminate\Support\ServiceProvider;
use Shopmade\Example\Models\Visit;

class ExampleServiceProvider extends ServiceProvider
{
    public function boot(Hooks $hooks): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'example');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        // Relations on core models, without editing core.
        Customer::resolveRelationUsing('visits', fn (Customer $customer) => $customer->hasMany(Visit::class));

        // Staff nav entry (same shape as config/core-tools.php). Hidden when the route does not exist.
        $hooks->tool([
            'slug' => 'visits',
            'label' => 'Visits',
            'group' => 'Work',
            'route' => 'visits.index',
            'pattern' => 'visits.*',
            'view_roles' => ['super_admin', 'admin', 'user'],
            'manage_roles' => ['super_admin', 'admin'],
        ]);

        // A card on the staff dashboard, a panel on the customer page.
        $hooks->dashboardCard('example::dashboard-card', fn ($user) => ['visits' => Visit::today()->get()]);
        $hooks->customerPanel('example::customer-panel', fn (Customer $customer) => ['visits' => $customer->visits]);

        // Client portal: a tab and a section on the portal home.
        $hooks->portalTab('Visits', 'portal.visits.index', 'portal.visits.*', icon: 'calendar-check');
        $hooks->portalSection('example::portal-next-visit', fn ($user) => ['visit' => Visit::nextFor($user)]);

        // A package state for <x-status>, drawn like a core state.
        $hooks->status('on_site', like: 'running', label: 'On site');

        // An MCP tool on the site's connector (a subclass of App\Core\Mcp\Tools\Tool).
        $hooks->mcpTool('visits_list', Mcp\VisitsListTool::class);
    }
}
```

| Hook | Renders | Closure receives |
|---|---|---|
| `tool(array)` | Staff sidebar entry (throws on a malformed entry) | |
| `dashboardCard(view, ?closure)` | Card on `/dashboard` | the signed-in `User` |
| `customerPanel(view, ?closure)` | Panel on the customer page (3-column grid) | the `Customer` |
| `portalTab(label, route, ?pattern, icon)` | Client portal tab (Lucide icon name) | |
| `portalSection(view, ?closure)` | Section on the portal home | the signed-in client `User` |
| `mcpTool(name, class)` | MCP tool; a core tool of the same name wins | |
| `status(state, like, ?label)` | `<x-status>` chip in the colors of a core state | |

Each closure returns the view's data array.

## Routes and access

Wrap staff routes in `['web', 'auth', 'can:staff.access']` and portal routes in `['web', 'auth', 'can:portal.access']` with the `portal.` name prefix. Check record ownership in portal controllers: a client sees only the customers in `$user->customers`. Use the `can-manage-core:<slug>` middleware for writes when your tool declares `manage_roles`.

## Views and styles

Extend `layouts.app` (staff) or `layouts.portal` (clients) and use the core Blade components (`x-card`, `x-button`, `x-field`, `x-input`, `x-status`, `x-empty`, `x-page-header`). Tailwind compiles classes found in `vendor/*/shopmade-*/resources/views`, so name your package `<vendor>/shopmade-<name>` and keep its views there; the classes are picked up on the next `npm run build`. Any other layout needs an `@source` line in `resources/css/app.css`.

## MCP tools

Extend `App\Core\Mcp\Tools\Tool`: `name()`, `title()`, `description()`, `inputSchema()` (JSON Schema, enforced before `handle()`), `handle()` returning a `ToolResult`, and `scope()` (`Operator` reads, `Director` writes; the default is `Director`). Override `annotations()` for write tools so clients see `readOnlyHint: false`.

## Plugins or packages

A plugin (`plugins/<slug>/plugin.json`, see `plugins/customer-notes`) is a small feature kept in this install's repo and switched on and off under **Admin → Plugins**. A package is versioned and installed with Composer. Ship anything reused across installs as a package.
