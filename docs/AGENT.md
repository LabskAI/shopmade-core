# Shopmade core: agent brief

Read this before coding.

## What this repo is

The open-source core of Shopmade: login and roles, customers, the client portal, the public website with its editor and MCP server, plugins and the package hook API. Business features (jobs, invoicing, shop, CRM, AI, outreach) are separate Composer packages in their own repos and must not be added here.

## Rules

1. Core never names a package. A package plugs in through `App\Core\Packages\Hooks` from its service provider ([packages.md](packages.md)); if a package needs a new extension point, add a generic hook here.
2. Roles: `super_admin`, `admin`, `user` (staff), `client` (portal). Capabilities live in `config/capabilities.php`.
3. Styles are Tailwind v4 compiled by Vite (`resources/css/app.css` for the app, `auth.css` for sign-in pages). Reuse the tokens in `resources/css/theme.css`; it wipes Tailwind's default palette on purpose.
4. Every website save goes through `App\Core\Front\FrontEditor`, which validates (a never-write phrase refuses the save), writes inside `content/<company>`, records a `front_revisions` row and commits the change in `content/`'s own git repo.
5. Write a test for each behavior you change; run the narrowest Pest file, then `vendor/bin/pint`.
6. Never commit secrets or a real customer's data. Seed data uses `example.com`.

## Where to look

| Need | Open |
|---|---|
| Install and run | [`../README.md`](../README.md) |
| Package hooks | [`packages.md`](packages.md) |
| Website and MCP tools | [`front/README.md`](front/README.md) |
