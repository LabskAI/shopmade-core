# Public fronts — the front kit

A company's public site, served by its own Shopmade install, from `fronts/<company>/` (`<company>` is `config('agents.company')`, env `AGENTS_COMPANY`, default `demo`). Edited under Admin → Website, from Claude over the site's MCP server, or by hand in the repo. The kit makes the site readable by people and by AI agents from day one. `fronts/demo/` is the reference front: the fictional "Demo Home Services", EN/ES, home and services pages, four skills (`brand-voice`, `design-direction`, `no-claims`, `design-taste`).

## Folder

```
fronts/<company>/
  facts.yaml              name, legal_name, phone, email, cities[], services[{slug, name}], languages[], hours[],
                          never_write[], social[], discovery{ai_search, ai_training}, address{publish, street, locality, region, postal_code, country}
  pages/<slug>.<lang>.md  YAML frontmatter between --- lines, then the markdown body
  skills/<name>.md        frontmatter name + description; guidance for whoever edits the site, never rendered
  theme/tokens.css        CSS custom properties: colors, type scale, spacing, radius
  lang/<lang>.yaml        UI strings (labels, day names); the kit's English defaults fill gaps
  assets/                 images, logo (not served yet)
```

- **facts.yaml** — the first language is the default and is served without a `/<lang>` prefix. Hours are schema.org `openingHours` strings (`"Mo-Fr 08:00-18:00"`); day codes are translated with `days:` in the lang file. A service name is text or one text per language (`{en: House cleaning, es: Limpieza de casas}`). Quote `never_write` entries that start with `#` (`"#1"`), or YAML reads them as comments.
- **Page frontmatter** — `title` and `description` (required), `layout` (`home` | `page`), `canonical?`, `noindex?` (kept out of the sitemap and llms files, and sent as `X-Robots-Tag: noindex` with both the HTML and the `Accept: text/markdown` response), `nav?: false`, `nav_label?`, `order?`, `cities`/`areas` (must be in `facts.cities`), `service`/`services` (slugs from `facts.services`; each adds a `Service` JSON-LD block), `forms[]` (parity-checked, not rendered yet), `jsonld?` (an object or a list).
- **Body** — markdown. Raw HTML is stripped and unsafe links (`javascript:` and the like) are dropped. Link to another page by its slug: `[Services](services)` points at that page in the same language.

Everything else — layout, nav and footer from facts, the stylesheet, discovery files — is the kit's (`app/Core/Front`, `resources/views/front`) and is not editable per company (ask for a plugin instead).

## Serving

`routes/front.php` serves the front under `/{AGENTS_FRONT_PREFIX}` (default `/site`) on every host (route names `front.*`) and, when `AGENTS_FRONT_HOST` is set, also at the root of that host (`front.host.*`). Canonical, hreflang, sitemap and llms URLs use the front host when it is set.

| Path (below the front root) | Serves |
|---|---|
| `/` | home in the default language |
| `/{lang}` | home in another language; `/en` (default) redirects 301 to `/` |
| `/{slug}`, `/{lang}/{slug}` | a page; `/home` and `/en/…` redirect 301 to the canonical URL, keeping the query |
| `/robots.txt`, `/sitemap.xml`, `/llms.txt`, `/llms-full.txt` | generated discovery files |

- Unknown pages and languages are 404.
- Content is read from `fronts/<company>` under `config('agents.paths.repo')` (default `base_path()`).
- Public pages are stateless: the front routes run without the session and CSRF middleware, so they set no cookies.
- For the bundled `demo` company, every front response, 404s included, carries `X-Robots-Tag: noindex, nofollow` (it replaces a page's own `noindex`).
- Deployment: on the front host, nginx `try_files $uri` serves the static `public/robots.txt` before Laravel. Send `/robots.txt` to `index.php` in that server block.

## What the kit generates

- `robots.txt`: a `User-agent: *` group with `Content-Signal: search=yes, ai-input=<ai_search>, ai-train=<ai_training>`, one group for AI search and assistant crawlers (OAI-SearchBot, ChatGPT-User, Claude-SearchBot, Claude-User, PerplexityBot, Perplexity-User) and one for training crawlers (GPTBot, ClaudeBot, Google-Extended, Applebot-Extended, CCBot, meta-externalagent), each `Allow` or `Disallow` per `facts.discovery`, and `Sitemap:`.
- `sitemap.xml`: every indexable page in every language, with `xhtml:link` hreflang alternates and `x-default`.
- `llms.txt` (name, summary, facts, pages per language, links to the full text and the sitemap) and `llms-full.txt` (every indexable page's markdown).
- Markdown negotiation: a request that prefers `text/markdown` gets the page's frontmatter and body (`Content-Type: text/markdown`); HTML and markdown responses both send `Vary: Accept`, and for a `noindex` page both send `X-Robots-Tag: noindex` (the markdown has no robots meta tag).
- JSON-LD: `LocalBusiness` from facts (city, region and country only unless `facts.address.publish: true`; never a rating), a `Service` block per service the page names, and the page's own `jsonld` with rating, review, award and certification keys removed. JSON is escaped so it cannot close its script element.
- Every page: canonical link, hreflang alternates, `<link rel="alternate" type="text/markdown">`, a `Link` header pointing at the sitemap and llms.txt, the language switcher, and `theme/tokens.css` inlined.

Not built yet: forms → `LeadIntake` (Turnstile, honeypot, time-to-submit, duplicate check), the chat panel bound to an office agent, `/.well-known/mcp/server-card.json`, `/.well-known/mcp.json`, `/.well-known/api-catalog`, `/.well-known/agent-skills/index.json`, `Link` headers for those, `FAQPage` JSON-LD, `theme/components/*.blade.php` overrides, serving `assets/`,.

## Rules enforced by `php artisan front:lint <company>`

Deterministic; every message names the file and quotes the offending text. Errors exit 1, warnings do not.

- **never_write** — no phrase from `facts.never_write` in page bodies, frontmatter (all strings, `jsonld` included) or `lang/*.yaml`. Case-insensitive whole words; a space in a phrase matches any whitespace.
- **cities / services** — frontmatter `city`/`cities`/`area`/`areas` must be in `facts.cities`; `service`/`services` must be slugs in `facts.services`. Body text is not checked for place names.
- **currency** — no amounts in page bodies or frontmatter: `$120`, `US$50`, `USD 50`, `50 USD`, `€`/`£`/`¥`, "dollars", "dólares", "euros" (prices are the office agent's business, behind approval).
- **frontmatter** — `title` and `description` on every page; frontmatter must be valid YAML.
- **parity** — a page missing in one of `facts.languages` is a warning; language versions with a different `layout` or `forms` are an error.
- **jsonld** — no `aggregateRating`, `review`, `award`, `hasCertification`, `hasCredential` (or objects of those types) in frontmatter `jsonld`.
- **pages / languages** — page files must be named `<slug>.<lang>.md`, in a language listed in `facts.languages`.
- **facts** — facts.yaml must exist and parse; name, languages, cities, services, never_write, discovery and address.publish must have the right shape.
- **discovery** — robots.txt, sitemap.xml (well-formed), llms.txt, llms-full.txt, JSON-LD and every page body render without an exception.
- **skills / layout** (warnings) — a skill without a name or description, or named differently from its file; an unknown layout.

Every save from the website editor or MCP returns its report.

## Readiness check

`App\Core\Front\FrontReadiness::check($baseUrl, $company, $query = [], $basePath = null)` runs the agent-readiness checks over HTTP: home answers 200 with a `<title>`, every page answers 200, robots.txt has `Sitemap:` and `Content-Signal:`, sitemap.xml lists every indexable page with its hreflang alternates, llms.txt exists, `Accept: text/markdown` returns frontmatter with `Vary: Accept`, and — only when `$query` is not empty, e.g. a signed link — `X-Robots-Tag: noindex`. `$baseUrl` is the front root (`https://example.com/site`, `https://example.com`); a bare host other than the front host gets `/{AGENTS_FRONT_PREFIX}` appended, and a query string in it (signed link) is sent with every request. The expected pages come from `fronts/<company>` under `$basePath` (default the repo).

## Website editor and MCP

**Admin → Website** (`/admin/site`, routes `site.*`, capability `site.edit`) edits these files: Pages (with per-page history and restore), Facts, Theme (values of the custom properties `theme/tokens.css` already declares) and Discovery (`facts.discovery`, plus a readiness check of the live front); Forms says "not built yet". The MCP server exposes the same edits as tools (`pages_list`, `page_get`, `page_create`, `page_save`, `page_delete`, `page_history`, `page_restore`, `facts_get`, `facts_save`, `theme_get`, `theme_save`, `site_lint`); reads need the `operator` scope, writes `director`.

Both go through `App\Core\Front\FrontEditor`. Each save writes one file inside `fronts/<company>` (slug and language checked, symlinks and anything resolving outside the folder refused), keeps unknown frontmatter and facts keys, is live at once, and records a `front_revisions` row with the new content (null for a delete). The first save of a file also records what was there before. Restore writes a saved version back as a new revision. Every save returns the linter's report.
