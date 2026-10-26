# Public fronts — the front kit

A company's public site, served by its own Shopmade install, from `content/<company>/` (`<company>` is `config('agents.company')`, env `AGENTS_COMPANY`, default `demo`). Edited under Admin → Website, from Claude over the site's MCP server, or by hand in that folder. `content/` is kept out of the code (gitignored, a volume in Docker) and is its own git repository: every save is a commit, so a code deploy never touches client edits. On first boot `php artisan front:install` copies the starter site from `fronts/<company>/` (or `fronts/demo/`) into it. The kit makes the site readable by people and by AI agents from day one. `fronts/demo/` is the reference front: the fictional "Demo Home Services", EN/ES, home and services pages using the components (hero art, cards, steps, flow, FAQ, CTA and an inline form), a logo, `theme/components.css`, a reveal script, and four skills (`brand-voice`, `design-direction`, `no-claims`, `design-taste`).

## Folder

```
content/<company>/
  facts.yaml              name, legal_name, phone, email, cities[], services[{slug, name}], languages[], hours[],
                          never_write[], social[], discovery{ai_search, ai_training}, address{publish, street, locality, region, postal_code, country}
  pages/<slug>.<lang>.md  YAML frontmatter between --- lines, then the markdown body
  skills/<name>.md        frontmatter name + description; guidance for whoever edits the site, never rendered
  theme/tokens.css        CSS custom properties: colors, type scale, spacing, radius
  theme/components.css    optional brand styles for the kit components (see "Component CSS")
  lang/<lang>.yaml        UI strings (labels, day names, language_name); the kit's English defaults fill gaps
  assets/                 images, SVG art, icons, woff2 fonts, video, company scripts — served at /assets/<path>
```

- **facts.yaml** — the first language is the default and is served without a `/<lang>` prefix. Hours are schema.org `openingHours` strings (`"Mo-Fr 08:00-18:00"`); day codes are translated with `days:` in the lang file. A service name is text or one text per language (`{en: House cleaning, es: Limpieza de casas}`). Quote `never_write` entries that start with `#` (`"#1"`), or YAML reads them as comments.
- **Page frontmatter** — `title` and `description` (required), `layout` (`home` | `page`), `canonical?`, `noindex?` (kept out of the sitemap and llms files, and sent as `X-Robots-Tag: noindex` with both the HTML and the `Accept: text/markdown` response), `nav?: false`, `nav_label?`, `order?`, `cities`/`areas` (must be in `facts.cities`), `service`/`services` (slugs from `facts.services`; each adds a `Service` JSON-LD block), `hero?` (see "Hero"), `forms[]` (slugs from `facts.forms`, shown after the article; see "Forms"), `jsonld?` (an object or a list).
- **Body** — markdown plus the allowlisted components below. Raw HTML is stripped and unsafe links (`javascript:` and the like) are dropped. Link to another page by its slug: `[Services](services)` points at that page in the same language. `![Alt](assets/team.webp)` and `[Brochure](assets/brochure.svg)` point at company files.
- **More facts the kit reads** — `logo` (`{src, dark?, alt}`, files under assets/), `cta` (header button, see "Forms"), `forms[]` ("Forms"), `fonts.faces[]` (`{family, src: assets/….woff2, weight?, style?, stretch?, preload?}`, self-hosted only), `scripts[]` (`{src, defer?, module?, enabled?}`: `assets/….js` files or `https://aiformflow.com` URLs, nothing else), `footer.columns[]` (`{title, links: [{label, href | slug}]}`, shown before the kit's columns) and `footer.legal[]` (links under the copyright line). Labels may be text or one text per language.

Everything else — layout, nav and footer from facts, the base stylesheet, discovery files — is the kit's (`app/Core/Front`, `resources/views/front`) and is not editable per company. A widget only one company needs (a calculator, a booking tool) belongs in a package that plugs in through `Hooks`, not in core.

## Components

A component is a fence: `:::name attr="value" …` on its own line, its content (markdown, or child fences), and a line with `:::` alone. Only the names below render, only through the kit's Blade views (`resources/views/front/components`). Everything is escaped: attributes are plain text (quotes, `<`, `>` and `&` come out as text), `class` and `variant` keep lowercase tokens (`[a-z][a-z0-9_-]*`), links go through the same check as markdown links (`https://`, `http://`, `mailto:`, `tel:`, `#hash`, `/path`, a page slug, or an asset; anything else is dropped) and images must be files under `assets/`. An unknown fence, an unclosed one, or a child in the wrong parent is shown as plain text, and `front:lint` warns about unknown fences and attributes. Fences nest (a `:::cards` inside a `:::section`) up to eight levels.

Shared attributes: `variant` (adds `front-<name>--<variant>`), `class` (extra tokens for `theme/components.css`) and, where listed, `id` (an anchor).

| Fence (aliases) | Attributes | Children | Renders |
|---|---|---|---|
| `section` | `kicker`, `image`, `alt`, `id` | any markdown and components | a `<section>`; with `image`, text and picture side by side |
| `cards` (`card-grid`) | `id` | `card` | a responsive grid of cards; text before the cards is the intro |
| `card` | `title`, `href`, `icon`, `image`, `alt`, `image2`, `alt2`, `chips`, `chip_variant`, `button_label`, `id` | — | a card: optional picture, icon, chips, title (a link when `href` is set without `button_label`), copy, second picture, and a `.btn` button (`button_label` + `href`) |
| `stat` | `value`, `label` | — | a figure and its label |
| `faq` (`accordion`) | `id` | `item` | questions that open and close (`<details>`) |
| `item` | `question` | — | one question; the content is the answer |
| `cta` (`cta-band`) | `title`, `href`, `label`, `secondary_href`, `secondary_label`, `image`, `id` | — | a call-to-action band with one or two buttons |
| `quote` (`testimonial`) | `author`, `role` | — | a quotation with attribution |
| `steps` | `id` | `step` | a numbered list of steps |
| `step` | `title`, `icon`, `chip`, `time`, `status`, `image`, `alt` | — | one step (also used inside `flow` and `timeline`) |
| `flow` | `label` (accessible name), `id` | `step` | cards joined by arrows: a process or system diagram; `chip` puts a small label on a step |
| `proof` | `href`, `label`, `id` | `card` | a list of points with icons, and an optional "more" link |
| `timeline` | `title`, `where`, `span`, `note`, `id` | `stat`, `step` | a dated sequence: stats on top, then steps (`time`, `title`, `status`) |
| `form` | `slug` | — | the form from `facts.forms` with that slug, placed here (see "Forms"); content becomes its intro |

- **icon** — a kit icon name (Phosphor regular, vendored, no CDN): `arrow-right`, `arrow-up-right`, `briefcase`, `buildings`, `calendar`, `caret-down`, `caret-right`, `chart`, `chat`, `check`, `check-circle`, `clock`, `code`, `envelope`, `gear`, `globe`, `heart`, `house`, `lightning`, `lock-key`, `map-pin`, `package`, `phone`, `robot`, `rocket`, `shield-check`, `sign-in`, `sparkle`, `storefront`, `translate`, `users`, `wrench`, `x`; or a company image, `icon="asset:icons/spark.svg"` (shown as an `<img>`). Unknown names are dropped.
- **chips** — short labels separated by `|`: `chips="Live|Built by Example Co"` (at most six, each escaped). `chip_variant="live"` adds `front-chip--live` to style them.
- **image**, **image2** — `assets/…` (or `asset:…`) image files; width and height come from the file (for SVG, its `width`/`height` or `viewBox`).

Example (a services grid and a product card):

```markdown
:::section kicker="What we do" id="services"
## Services

:::cards
:::card title="Websites" icon="code"
Fast sites you can edit yourself.
:::
:::card title="Assistants" icon="asset:icons/assistant.svg"
Answers for your customers, day and night.
:::
:::
:::

:::card title="Example App" chips="Live|Built by Example Co" chip_variant="live" image="assets/app-1.webp" alt="The dashboard" image2="assets/app-2.webp" alt2="The mobile view" href="https://app.example.com" button_label="Visit Example App"
**For:** small shops. **Today:** 40 stores.
:::

:::flow label="How it works"
:::step title="Website" icon="globe"
:::
:::step title="Assistant" icon="robot" chip="Built by Example Co"
:::
:::step title="Your inbox" icon="envelope"
:::
:::
```

In `llms-full.txt` and the `Accept: text/markdown` response, components are written as plain markdown (headings, numbered lists, quotes, image and button links); no `:::` line is left.

### Component CSS

`theme/components.css` is inlined after the kit stylesheet. It styles one class contract: `front-<name>` on every component (`front-card`, `front-steps`…), `front-<name>--<variant>` for `variant="…"`, `front-<name>__<part>` for parts (`front-card__title`, `front-card__image`, `front-flow__arrow`, `front-form__embed`…), `front-chip`/`front-chip--<chip_variant>`, `front-kicker`, `front-icon`, `.btn`/`.btn-primary`/`.btn-outline`, plus the page's `class="…"` tokens. Components that suit an entrance animation carry `data-reveal` for a company script (see `facts.scripts`). Rules: no `<`, no backslash escapes, no `@import`/`@charset`/`@namespace`, no `expression(`, `image(`, `image-set(`, `javascript:`, `behavior:` or `-moz-binding` (in any case, with or without whitespace before `(` or `:`), and `url()` only for files under assets/ (`url("assets/pattern.svg")`, rewritten to the file's URL). A save that breaks a rule is refused; a file committed by hand is linted (error) and the offending parts are dropped when served.

### Hero

Frontmatter `hero` puts media at the top of the page: `image` (any served image type under assets/, SVG included; the size is read from the file), or a muted looping video (`mp4`/`webm` with a `poster`), plus `alt`, `kicker` (a short line above the headline), `caption` and `position` (`"x% y%"` only, the image's focus). On `layout: home` the markdown before the first `## ` heading or fence (the `# H1` and intro) sits beside the media; on other pages the media sits above the article.

```yaml
hero:
  image: assets/hero-art.svg
  alt: ""
  kicker: Software for small businesses
```

## Forms

AIFormFlow forms are declared once in `facts.forms` and placed by pages:

```yaml
forms:
  - slug: contact             # lowercase slug, used by pages and #form-<slug> links
    name: {en: Contact, es: Contacto}
    title: {en: Book a call}  # optional heading; else lang `form_title`, else name
    lead: {en: We answer within a day.}   # optional line under the heading; else lang `form_lead`
    status: live              # live embeds the form; draft/off only shows the hosted link (if any)
    client: example-co        # AIFormFlow client id (or taken from a <client>.aiff.dev hosted URL)
    form: {en: contact-en, es: contact-es}  # AIFormFlow form id, per language or one for all
    hosted: https://example-co.aiff.dev/contact   # optional https link: no-JS and failure fallback
    theme: light              # light | dark | auto
cta:
  label: {en: Book a call, es: Agenda una llamada}
  target: "#form-contact"     # or a page slug, /path or https URL
```

- A page lists forms in frontmatter `forms: [contact]` (shown after the article), or places one inside the body with `:::form slug="contact"` followed by `:::` (e.g. in a section next to cards). A form placed inline is not repeated after the article.
- `status: live` renders `<div data-aiformflow data-client data-form data-theme data-lang>`; `https://aiformflow.com/embed/v1/form.js` is loaded only on pages that show a live form. Without `live` (or a client), the hosted link is shown as a button, and a form with neither is not shown. If the embed has not mounted after a few seconds, a fallback message (and the hosted link) appears.
- Header CTA: a `#form-<slug>` target stays on the page when the page shows that form, else points at the first page in the language that does.
- UI strings (lang files; English defaults): `open_form`, `open_form_page`, `form_title`, `form_lead`, `form_error`, `form_error_help`, `prefer_to_talk`, `contact`, `legal`. `language_name` labels the language switcher (default: the language's own name for en, es, pt, fr, de, it, nl; else the code).
- `front:lint` checks `facts.forms` (slug, status, client and form ids, https `hosted`) and warns about pages that name an undeclared form.

## Serving

`routes/front.php` serves the front under `/{AGENTS_FRONT_PREFIX}` (default `/site`) on every host (route names `front.*`) and, when `AGENTS_FRONT_HOST` is set, also at the root of that host (`front.host.*`). Canonical, hreflang, sitemap and llms URLs use the front host when it is set.

| Path (below the front root) | Serves |
|---|---|
| `/` | home in the default language |
| `/{lang}` | home in another language; `/en` (default) redirects 301 to `/` |
| `/{slug}`, `/{lang}/{slug}` | a page; `/home` and `/en/…` redirect 301 to the canonical URL, keeping the query |
| `/robots.txt`, `/sitemap.xml`, `/llms.txt`, `/llms-full.txt` | generated discovery files |
| `/assets/{path}` | a company file from `assets/` (see below) |
| `/favicon.ico`, `/favicon.svg`, `/apple-touch-icon.png`, `/icon-192.png`, `/icon-512.png`, `/site.webmanifest` | the same names from `assets/icons/`, linked from every page when present |

- Unknown pages and languages are 404.
- Assets: only `svg png jpg jpeg webp avif gif mp4 webm woff2 ico js webmanifest`, served with their fixed type, `X-Content-Type-Options: nosniff`, a sandboxing CSP and a one-year immutable cache (the kit links them with a `?v=<content hash>`). Paths with `..`, backslashes, hidden or odd segments, symlinks (file or folder) and anything resolving outside `assets/` are 404.
- Every HTML page sends a `Content-Security-Policy` with a fresh nonce: scripts only from the site itself, the nonce and AIFormFlow (`aiformflow.com`, `aiff.dev` and their subdomains), frames and connections only to AIFormFlow, `object-src 'none'`, `base-uri 'self'`. The kit's own pages have no inline script.
- Content is read from `content/<company>` (`config('agents.paths.fronts')`, env `AGENTS_CONTENT_DIR`) under `config('agents.paths.repo')` (default `base_path()`).
- Public pages are stateless: the front routes run without the session and CSRF middleware, so they set no cookies.
- For the bundled `demo` company, every front response, 404s included, carries `X-Robots-Tag: noindex, nofollow` (it replaces a page's own `noindex`).
- Deployment: on the front host, nginx `try_files $uri` serves the static `public/robots.txt` before Laravel. Send `/robots.txt` to `index.php` in that server block.

## What the kit generates

- `robots.txt`: a `User-agent: *` group with `Content-Signal: search=yes, ai-input=<ai_search>, ai-train=<ai_training>`, one group for AI search and assistant crawlers (OAI-SearchBot, ChatGPT-User, Claude-SearchBot, Claude-User, PerplexityBot, Perplexity-User) and one for training crawlers (GPTBot, ClaudeBot, Google-Extended, Applebot-Extended, CCBot, meta-externalagent), each `Allow` or `Disallow` per `facts.discovery`, and `Sitemap:`.
- `sitemap.xml`: every indexable page in every language, with `xhtml:link` hreflang alternates and `x-default`.
- `llms.txt` (name, summary, facts, pages per language, links to the full text and the sitemap) and `llms-full.txt` (every indexable page's markdown).
- Markdown negotiation: a request that prefers `text/markdown` gets the page's frontmatter and body (`Content-Type: text/markdown`); HTML and markdown responses both send `Vary: Accept`, and for a `noindex` page both send `X-Robots-Tag: noindex` (the markdown has no robots meta tag).
- JSON-LD: `LocalBusiness` from facts (city, region and country only unless `facts.address.publish: true`; never a rating), a `Service` block per service the page names, and the page's own `jsonld` with rating, review, award and certification keys removed. JSON is escaped so it cannot close its script element.
- Every page: canonical link, hreflang alternates, `<link rel="alternate" type="text/markdown">`, a `Link` header pointing at the sitemap and llms.txt, the language switcher, `theme/tokens.css` and `theme/components.css` inlined, self-hosted `@font-face` rules, the logo, the header CTA, the declared scripts (with the page nonce) and well-known icons.

Not built yet: a core lead intake for forms (`LeadIntake`: Turnstile, honeypot, time-to-submit, duplicate check; today forms are AIFormFlow embeds), the chat panel bound to an office agent, `/.well-known/mcp/server-card.json`, `/.well-known/mcp.json`, `/.well-known/api-catalog`, `/.well-known/agent-skills/index.json`, `Link` headers for those, `FAQPage` JSON-LD, `theme/components/*.blade.php` overrides.

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
- **components** (warnings) — an unknown `:::fence` (shown as text) or an attribute a component does not take.
- **forms** — `facts.forms` entries need a slug, a known status, valid client and form ids and an https `hosted` (errors); a page naming a form that is not declared (warning).
- **hero / assets** — a `hero` without media, or a hero, component image, asset icon, markdown `assets/…` link, logo, font face or script that is not a served file under assets/ (errors); a `hero.position` that is not `"x% y%"` (warning).
- **scripts / fonts** — `facts.scripts` may only name `assets/….js` files or `https://aiformflow.com` URLs; font faces need a family and a `.woff2` src (errors).
- **components_css** — `theme/components.css` breaks a rule from "Component CSS" (error).

Every save from the website editor or MCP returns its report.

## Readiness check

`App\Core\Front\FrontReadiness::check($baseUrl, $company, $query = [], $basePath = null)` runs the agent-readiness checks over HTTP: home answers 200 with a `<title>`, every page answers 200, robots.txt has `Sitemap:` and `Content-Signal:`, sitemap.xml lists every indexable page with its hreflang alternates, llms.txt exists, `Accept: text/markdown` returns frontmatter with `Vary: Accept`, and — only when `$query` is not empty, e.g. a signed link — `X-Robots-Tag: noindex`. `$baseUrl` is the front root (`https://example.com/site`, `https://example.com`); a bare host other than the front host gets `/{AGENTS_FRONT_PREFIX}` appended, and a query string in it (signed link) is sent with every request. The expected pages come from `content/<company>` under `$basePath` (default the repo).

## Website editor and MCP

**Admin → Website** (`/admin/site`, routes `site.*`, capability `site.edit`) edits these files: Pages (with per-page history and restore), Facts, Theme (values of the custom properties `theme/tokens.css` already declares) and Discovery (`facts.discovery`, plus a readiness check of the live front); Forms says "not built yet" (declare forms in facts.yaml). The MCP server exposes the same edits as tools (`pages_list`, `page_get`, `page_create`, `page_save`, `page_delete`, `page_history`, `page_restore`, `facts_get`, `facts_save`, `theme_get`, `theme_save`, `site_lint`) plus the front-kit tools; reads need the `operator` scope, writes `director`.

- `page_save` and `page_create` also take `layout` (`home` | `page`), `hero` (the keys from "Hero"; files must already exist under assets/; `{}` removes it) and `forms` (slugs declared in `facts.forms`; `[]` removes them). Anything else is refused before the file is written.
- `components_css_get` / `components_css_save` read and replace `theme/components.css` under the rules in "Component CSS" (at most 200 KB).
- `asset_put` writes one file under assets/ from base64 (`svg png jpg jpeg webp avif gif mp4 webm woff2 ico webmanifest`, at most 5 MB, content checked against the extension; no scripts — those arrive through git; an SVG with `<script>`, `on…=` event attributes, `javascript:` URLs, `foreignObject` or entity declarations is refused) and commits it to the content history.

`php artisan front:install --force [company]` replaces `content/<company>` with the shipped `fronts/<company>` after committing a snapshot of the current content to the content history (so an install whose volume still holds the demo can switch to a shipped front; the previous content stays one commit back). If that snapshot cannot be committed, the command stops with an error and deletes nothing. Without `--force`, existing content is never touched.

Both go through `App\Core\Front\FrontEditor`. Each save writes one file inside `content/<company>` (slug and language checked, symlinks and anything resolving outside the folder refused), keeps unknown frontmatter and facts keys, is live at once, records a `front_revisions` row with the new content (null for a delete) and commits the file in `content/`'s git history. A page save that contains a `never_write` phrase (in the body or any frontmatter value) is refused. The first save of a file also records what was there before. Restore writes a saved version back as a new revision. Every save returns the linter's report.
