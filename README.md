# Shopmade core

A small website and customer system for one business, built on Laravel. Run it in Docker, edit the site from the admin or from Claude over MCP, and add features as Laravel packages.

## Quick start

You need PHP 8.4+, Composer and Docker.

```bash
composer create-project labskai/shopmade-core mysite
cd mysite
docker compose up -d --build
docker compose exec app php artisan user:create-owner you@example.com "Your Name"
```

`create-project` asks which packages to include (jobs, invoicing, shop, CRM, AI client) and whether this is a local development install. The first start runs `composer install`, the asset build and the migrations, so give it a minute.

- Admin: [http://localhost:8000/login](http://localhost:8000/login), with the password `user:create-owner` printed
- Public site: [http://localhost:8000/site](http://localhost:8000/site)

### Connect Claude

```bash
claude mcp add --transport http shopmade http://localhost:8000/mcp/agents
```

Sign in as an admin when Claude asks. Claude can then read and edit the website. Every save is live, kept in the page history and committed to the git history in `content/`. Connectors are listed and revoked under **Admin → Connectors**.

### Add packages

```bash
docker compose exec app php artisan shopmade:install                     # pick from a list
docker compose exec app php artisan shopmade:install --packages=jobs,crm # or name them
docker compose exec app php artisan migrate --force
docker compose exec app npm run build
```

Writing your own package: [docs/packages.md](docs/packages.md).

## Other ways to install

### No PHP on your machine

```bash
git clone https://github.com/LabskAI/shopmade-core mysite && cd mysite
cp .env.example .env
docker compose up -d --build
```

The container runs `composer install` for you. A clone starts in production mode; set `APP_ENV=local` and `APP_DEBUG=true` in `.env` for development (this also allows Claude Code's localhost sign-in).

### Demo data

```bash
SEED_DEMO=1 docker compose up --build
```

Demo accounts `owner@`, `admin@`, `staff@` and `client@shopmade.test` all use the password `password`. Never seed a public install.

### A server, in one command

Point your domain's DNS at a fresh Ubuntu or Debian server, then run:

```bash
curl -fsSL https://raw.githubusercontent.com/LabskAI/shopmade-core/main/install.sh | sudo bash -s -- \
    --domain site.example.com --owner-email you@example.com --packages jobs,invoicing
```

[`install.sh`](install.sh) installs Docker if needed, puts the site in `/opt/shopmade` and starts it with Postgres and Caddy (automatic HTTPS). It prints the owner's password and the MCP URL.

- Leave out `--domain` to try it over plain HTTP on port 8080.
- Run it again to update or to add `--packages`; settings, database and content are kept.
- `/opt/shopmade/compose` runs `docker compose` on the install, e.g. `/opt/shopmade/compose logs -f app`.

### Easypanel, Coolify or another PaaS

Use the image `ghcr.io/labskai/shopmade-core:latest`, or build your own with packages baked in:

```bash
docker build --target production --build-arg SHOPMADE_PACKAGES=jobs,invoicing -t mysite .
```

It serves HTTP on port 8080 and runs the queue worker and scheduler in the same container (`RUN_WORKERS=0` turns them off). Migrations run on start. Give it:

| Setting | Value |
|---|---|
| `APP_KEY` | `php artisan key:generate --show` |
| `APP_URL`, `DB_*` | Your URL and Postgres connection |
| `AGENTS_OAUTH_ALLOW_LOOPBACK=true` | Lets Claude Code and other desktop MCP clients sign in |
| Volume `/var/www/shopmade/storage` | Uploads and logs |
| Volume `/var/www/shopmade/content` | The website, with its own git history (the first boot copies the starter site in) |

The Easypanel template is in [`docker/easypanel/`](docker/easypanel/).

## Connect Claude or ChatGPT to a live site

- **Claude:** add `https://<your-host>/mcp/agents` as a custom connector, or run `claude mcp add --transport http shopmade https://<your-host>/mcp/agents` from Claude Code (needs `AGENTS_OAUTH_ALLOW_LOOPBACK=true`, which `install.sh` and the Easypanel template set).
- **ChatGPT:** turn on developer mode in the Apps (connectors) settings, create a connector with the MCP URL `https://<your-host>/mcp/agents` and OAuth, and sign in as an admin. No client ID or secret needed.

## Settings worth knowing

| Variable | What it does |
|---|---|
| `AGENTS_COMPANY` | Which folder under `content/` is the live site (default `demo`) |
| `AGENTS_FRONT_HOST` | Serve the public site at the root of this domain instead of `/site` |
| `SEED_DEMO=1` | Load demo data on start |
| `RUN_WORKERS=0` | Production image only: don't run the queue worker and scheduler in the web container |
| `MAIL_MAILER`, `MAIL_HOST`, … | Outgoing mail (password resets, portal invites, contact form messages). The default `log` sends nothing; set `smtp` and its settings to send for real |

Without working mail, print a password reset link with `php artisan user:reset-link you@example.com`.

## What's in core

- Sign-in with four roles: `super_admin`, `admin`, `user` (staff) and `client` (portal), and password reset by email.
- A built-in contact form for the website; messages land in **Admin → Messages** and by email.
- Customers, client portal and client change requests.
- The public website: pages, facts, theme and SEO/AI discovery files in `content/<company>/` ([front kit](docs/front/README.md)).
- The website editor under **Admin → Website**, with page history and restore.
- An MCP server at `/mcp/agents` with OAuth 2.1.
- Plugins (`plugins/`) and a hook API for Composer packages ([docs/packages.md](docs/packages.md)).

## Develop

```bash
composer install && npm install
php artisan test --compact                        # SQLite in memory
php artisan test --compact -c phpunit.pgsql.xml   # Postgres database shopmade_testing
php artisan front:lint demo                       # lint one starter site
vendor/bin/pint
npm run dev
```

Stack: Laravel 13, PHP 8.5, PostgreSQL, Blade, Tailwind v4 and Alpine via Vite, Lucide icons, Pest. Postgres from `docker compose` is on `localhost:5433` (`shopmade` / `shopmade`).

| Path | What |
|---|---|
| `app/Core/Front` | Site rendering, linter, website editor |
| `app/Core/Mcp`, `app/Core/OAuth` | MCP server and its OAuth 2.1 server |
| `app/Core/Packages/Hooks.php` | Where packages plug into core screens and MCP |
| `app/Core/Plugins` | Plugin registry and loader for `plugins/*/plugin.json` |
| `content/<company>/` | The live site. Gitignored, with its own git history of every save |
| `fronts/<company>/` | Starter sites copied into `content/` on first boot (`demo` is the sample) |
| `docs/` | Agent brief, package guide, front kit |

## Upgrading from `fronts/`

Older installs kept the live site in `fronts/<company>/`, usually on a volume at `/var/www/shopmade/fronts`. Before starting the new image, move that volume (or its `<company>` folder) to `/var/www/shopmade/content`. The starter site is only copied into an **empty** `content/<company>`, so starting on an empty volume replaces your site with the starter, and leaving the old volume on `fronts/` loses your edits when the container is recreated.

## License

MIT. See [LICENSE](LICENSE).
