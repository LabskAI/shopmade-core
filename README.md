# Shopmade core

A small website and customer system for one business, built on Laravel. You run it in Docker, edit the website from the admin screens or from Claude over MCP, and add features as Laravel packages.

Core ships:

- Sign-in with four roles: `super_admin`, `admin`, `user` (staff) and `client` (portal).
- Customers, client portal access and client change requests.
- The public website: pages, facts, theme and SEO/AI discovery files, served from `content/<company>/` ([front kit](docs/front/README.md)).
- The website editor under **Admin → Website**, with page history and restore.
- An MCP server (`/mcp/agents`) with OAuth 2.1, so Claude can read and edit the site.
- A plugin system for small in-repo features (`plugins/`) and a hook API for Composer packages ([docs/packages.md](docs/packages.md)).

## Install

### On your computer, with Composer

You need PHP 8.4+, Composer and Docker.

```bash
composer create-project labskai/shopmade-core mysite dev-main \
    --repository='{"type":"vcs","url":"https://github.com/LabskAI/shopmade-core","no-api":true}'
cd mysite
docker compose up -d --build
docker compose exec app php artisan user:create-owner you@example.com "Your Name"
```

Once core is on Packagist this shortens to `composer create-project labskai/shopmade-core mysite`. `create-project` asks which public packages to include (jobs, invoicing, shop, CRM, AI client) and whether this is a local development install. Run `php artisan shopmade:install` later to add more, or `php artisan shopmade:install --packages=jobs,invoicing` to skip the questions. It adds each package's GitHub repo to `composer.json` and requires its first release, or its `main` branch until there is one.

No PHP on the machine? Clone instead: `git clone https://github.com/LabskAI/shopmade-core mysite && cd mysite && cp .env.example .env`, then continue with `docker compose up -d --build` (the container runs `composer install`). Add packages later from inside it: `docker compose exec app php artisan shopmade:install`.

Open [http://localhost:8000/login](http://localhost:8000/login) and sign in with the printed password. The public site is at [http://localhost:8000/site](http://localhost:8000/site); set `AGENTS_FRONT_HOST` to serve it at the root of its own domain, and `AGENTS_COMPANY` to pick the folder under `content/`.

A plain `git clone` starts with `APP_ENV=production` and `APP_DEBUG=false`; set `APP_ENV=local` and `APP_DEBUG=true` in `.env` for development (this also installs the dev dependencies and allows localhost OAuth callbacks).

For sample data instead, start with `SEED_DEMO=1 docker compose up --build`. Every demo account (`owner@`, `admin@`, `staff@`, `client@shopmade.test`) has the password `password`, so never seed a public install.

The first start runs `composer install`, `npm ci && npm run build` and the migrations. Postgres is on `localhost:5433` (`shopmade` / `shopmade`).

### On a server, with one command

On a fresh Ubuntu or Debian server, point your domain's DNS at it and run:

```bash
curl -fsSL https://raw.githubusercontent.com/LabskAI/shopmade-core/main/install.sh | sudo bash -s -- \
    --domain site.example.com --owner-email you@example.com --packages jobs,invoicing
```

[`install.sh`](install.sh) installs Docker if needed, downloads core to `/opt/shopmade/src`, writes the keys to `/opt/shopmade/.env`, builds the image with the packages you picked and starts it with Postgres and Caddy (automatic HTTPS). It prints the owner's password and the MCP connector URL. Leave out `--domain` to try it over plain HTTP on port 8080 (proxy headers are then not trusted). Run it again to update; your settings, database and content stay. `/opt/shopmade/compose` runs `docker compose` on the install (`/opt/shopmade/compose logs -f app`).

### On Easypanel, Coolify or another PaaS

Build the image from this repo (the Dockerfile's last stage is the production image). From the first tagged release on, it is also published as `ghcr.io/labskai/shopmade-core` (GitHub creates that package private; it is made public after the first publish).

```bash
docker build --target production -t shopmade-core .
docker build --target production --build-arg SHOPMADE_PACKAGES=jobs,invoicing -t mysite .   # with packages
```

It bakes in the code, `vendor/` and the asset build, and serves HTTP on port 8080, with the queue worker and scheduler in the same container (`RUN_WORKERS=0` turns them off if you run them as their own containers). Give it:

- `APP_KEY` (from `php artisan key:generate --show`), `APP_URL` and the `DB_*` settings as environment variables;
- `AGENTS_OAUTH_ALLOW_LOOPBACK=true` if Claude Code or another desktop MCP client should connect (they sign in through an `http://localhost` callback; `install.sh` and the Easypanel template set it);
- volumes on `/var/www/shopmade/storage` and `/var/www/shopmade/content` (the website content lives there, with its own git history; the first boot copies the starter site in).

Migrations run when the container starts. The Easypanel template is in [`docker/easypanel/`](docker/easypanel/).

### Upgrading a site that lived in `fronts/`

Before `content/`, the live site was `fronts/<company>/`, usually on a volume mounted at `/var/www/shopmade/fronts`. Before you start the new image, move that volume (or copy its `<company>` folder) to `/var/www/shopmade/content`. `front:install` only copies the starter into an **empty** `content/<company>`, so if you start with a new empty volume the starter site goes live in place of yours. If you leave the old volume on `fronts/`, your edits stay outside `content/` and are lost when the container is recreated. On the first boot with content already in place, `front:install` commits a full snapshot of it as the start of the git history.

## Connect Claude or ChatGPT

Add `https://<your-host>/mcp/agents` as a custom connector in Claude. From Claude Code on the same machine as a local install, run `claude mcp add --transport http shopmade http://localhost:8000/mcp/agents` (local installs allow its localhost sign-in callback). For a server, run the same command with `https://<your-host>/mcp/agents`; that needs `AGENTS_OAUTH_ALLOW_LOOPBACK=true`, which `install.sh` and the Easypanel template set. Sign in as an admin when Claude asks; the connector then reads (`operator`) and edits (`director`) the website. Every save is live, kept in the page history and committed to the git history in `content/`. Connectors are listed and revoked under **Admin → Connectors**.

In ChatGPT, turn on developer mode in the Apps (connectors) settings and create a new app or connector: give it a name, set the MCP server URL to `https://<your-host>/mcp/agents`, choose OAuth, and sign in as an admin when ChatGPT opens your site. ChatGPT registers itself; no client ID or secret to paste, and no extra env settings.

## Add a feature

Pick packages at install time (above), or add one to a running stack, then run its migrations and rebuild the assets so its Tailwind classes are compiled:

```bash
docker compose exec app php artisan shopmade:install --packages=jobs   # or: composer require vendor/shopmade-example
docker compose exec app php artisan migrate --force
docker compose exec app npm run build
```

On a server installed with `install.sh`, rerun it with `--packages` instead; it rebuilds the image and migrates on start.

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
