# Shopmade core

A small website and customer system for one business, built on Laravel. You run it in Docker, edit the website from the admin screens or from Claude over MCP, and add features as Laravel packages.

Core ships:

- Sign-in with four roles: `super_admin`, `admin`, `user` (staff) and `client` (portal).
- Customers, client portal access and client change requests.
- The public website: pages, facts, theme and SEO/AI discovery files, served from `content/<company>/` ([front kit](docs/front/README.md)).
- The website editor under **Admin → Website**, with page history and restore.
- An MCP server (`/mcp/agents`) with OAuth 2.1, so Claude can read and edit the site.
- A plugin system for small in-repo features (`plugins/`) and a hook API for Composer packages ([docs/packages.md](docs/packages.md)).

## Run it

```bash
cp .env.example .env
docker compose up --build
docker compose exec app php artisan user:create-owner you@example.com "Your Name"
```

Open [http://localhost:8000/login](http://localhost:8000/login) and sign in with the printed password. The public site is at [http://localhost:8000/site](http://localhost:8000/site); set `AGENTS_FRONT_HOST` to serve it at the root of its own domain, and `AGENTS_COMPANY` to pick the folder under `content/`.

The stack starts with `APP_ENV=production` and `APP_DEBUG=false`. For local development set `APP_ENV=local` and `APP_DEBUG=true` in `.env` (this also installs the dev dependencies and allows localhost OAuth callbacks).

For sample data instead, start with `SEED_DEMO=1 docker compose up --build`. Every demo account (`owner@`, `admin@`, `staff@`, `client@shopmade.test`) has the password `password`, so never seed a public install.

The first start runs `composer install`, `npm ci && npm run build` and the migrations. Postgres is on `localhost:5433` (`shopmade` / `shopmade`).

## Deploy

`compose.yaml` bind-mounts the checkout, which suits a server you manage by hand and local work. For a registry or a PaaS (Easypanel, Coolify, …) build the self-contained image instead:

```bash
docker build --target production -t shopmade-core .
```

It bakes in the code, `vendor/` and the asset build, and serves HTTP on port 8080. Give it:

- `APP_KEY` (from `php artisan key:generate --show`), `APP_URL` and the `DB_*` settings as environment variables;
- volumes on `/var/www/shopmade/storage` and `/var/www/shopmade/content` (the website content lives there, with its own git history; the first boot copies the starter site in);
- a second container from the same image running `php artisan queue:work database` with `SKIP_SETUP=1`, and a third running `php artisan schedule:work` with `SKIP_SETUP=1`.

Migrations run when the web container starts.

### Upgrading a site that lived in `fronts/`

Before `content/`, the live site was `fronts/<company>/`, usually on a volume mounted at `/var/www/shopmade/fronts`. Before you start the new image, move that volume (or copy its `<company>` folder) to `/var/www/shopmade/content`. `front:install` only copies the starter into an **empty** `content/<company>`, so if you start with a new empty volume the starter site goes live in place of yours. If you leave the old volume on `fronts/`, your edits stay outside `content/` and are lost when the container is recreated. On the first boot with content already in place, `front:install` commits a full snapshot of it as the start of the git history.

## Connect Claude

Add `https://<your-host>/mcp/agents` as a custom connector in Claude. Sign in as an admin when Claude asks; the connector then reads (`operator`) and edits (`director`) the website. Every save is live, kept in the page history and committed to the git history in `content/`. Connectors are listed and revoked under **Admin → Connectors**.

## Add a feature

Install a feature package with Composer, then run its migrations and rebuild the assets so its Tailwind classes are compiled:

```bash
docker compose exec app composer require vendor/shopmade-example
docker compose exec app php artisan migrate
docker compose exec app npm run build
```

Writing your own package: [docs/packages.md](docs/packages.md).

## Develop

```bash
composer install && npm install
php artisan test --compact                        # SQLite in memory (phpunit.xml)
php artisan test --compact -c phpunit.pgsql.xml   # Postgres database shopmade_testing
php artisan front:lint demo                       # the website linter on one front
vendor/bin/pint
npm run dev
```

Stack: Laravel 13, PHP 8.5, PostgreSQL, Blade, Tailwind v4 and Alpine bundled by Vite, Lucide icons, Pest.

## Repo map

| Path | What |
|---|---|
| `app/Core/Front` | Front kit: site rendering, linter, website editor (`FrontEditor`) |
| `app/Core/Mcp`, `app/Core/OAuth` | The MCP server and its OAuth 2.1 server |
| `app/Core/Packages/Hooks.php` | Where packages plug into core screens and the MCP server |
| `app/Core/Plugins` | Plugin registry and loader for `plugins/*/plugin.json` |
| `content/<company>/` | A company's live site: `facts.yaml`, pages, skills, theme tokens. Gitignored, with its own git history of every save |
| `fronts/<company>/` | Starter sites `front:install` copies into `content/` on first boot (`demo` is the sample) |
| `plugins/customer-notes` | Example plugin |
| `docs/` | Agent brief, package guide, front kit |

## License

MIT. See [LICENSE](LICENSE).
