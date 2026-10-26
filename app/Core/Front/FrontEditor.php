<?php

namespace App\Core\Front;

use App\Core\Agents\Actor;
use App\Core\Agents\AgentRefusedException;
use App\Models\FrontRevision;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Edits the company front (fronts/<company>) in place, from the dashboard (Admin → Website) and from MCP. Every save
 * writes the live file and records a FrontRevision with the new content, so any earlier version can be restored. The
 * first save of a file also records what was there before, so the original stays restorable.
 *
 * Request input never becomes a path: pages are named by a validated slug and language, every file is one of
 * pages/<slug>.<lang>.md, facts.yaml, theme/tokens.css, theme/components.css or a FrontAssets-validated path under
 * assets/, and every write is checked to stay inside fronts/<company>.
 */
class FrontEditor
{
    public const FACTS_FILE = 'facts.yaml';

    public const THEME_FILE = 'theme/tokens.css';

    public const COMPONENTS_CSS_FILE = 'theme/components.css';

    /**
     * Largest asset asset_put writes (decoded bytes).
     */
    public const ASSET_MAX_BYTES = 5_000_000;

    /**
     * Asset types the editor writes: images, fonts, video, icons and the web manifest. Scripts only arrive through git.
     *
     * @var list<string>
     */
    public const ASSET_TYPES = ['svg', 'png', 'jpg', 'jpeg', 'webp', 'avif', 'gif', 'mp4', 'webm', 'woff2', 'ico', 'webmanifest'];

    /**
     * The facts the editor writes; everything else in facts.yaml is kept as it is.
     *
     * @var list<string>
     */
    public const FACT_FIELDS = ['name', 'phone', 'email', 'cities', 'services', 'languages', 'hours', 'never_write', 'discovery'];

    public const HISTORY_LIMIT = 50;

    private const LOCK_SECONDS = 60;

    private const LOCK_WAIT_SECONDS = 15;

    public function __construct(
        private FrontKit $kit,
        private FrontLinter $linter,
        private FrontThemeTokens $tokens,
        private FrontFactsYaml $factsYaml,
        private FrontContentHistory $history,
        private FrontAssets $assets,
        private FrontComponentsCss $componentsCss,
    ) {}

    public function company(): string
    {
        return (string) config('agents.company');
    }

    /**
     * @return list<array{slug: string, lang: string, path: string, frontmatter: array<string, mixed>, body: string}>
     */
    public function pages(): array
    {
        return $this->kit->pages($this->company());
    }

    /**
     * @return array{slug: string, lang: string, path: string, frontmatter: array<string, mixed>, body: string}|null
     */
    public function page(string $slug, string $lang): ?array
    {
        return $this->kit->page($this->company(), $slug, $lang);
    }

    /**
     * @return list<string>
     */
    public function languages(): array
    {
        return $this->kit->languages($this->company());
    }

    /**
     * @return array<string, mixed>
     */
    public function facts(): array
    {
        return $this->kit->facts($this->company());
    }

    /**
     * The theme's custom properties with their current values, or null when the front has no theme/tokens.css.
     *
     * @return array<string, string>|null
     */
    public function themeTokens(): ?array
    {
        $css = $this->kit->theme($this->company());

        return $css === null ? null : $this->tokens->parse($css);
    }

    /**
     * The front-relative file of a page, after checking the slug and the language code.
     */
    public function pageFile(string $slug, string $lang): string
    {
        if (preg_match('/^'.FrontKit::SLUG_PATTERN.'\z/', $slug) !== 1 || preg_match('/^'.FrontKit::LANGUAGE_PATTERN.'\z/', $lang) !== 1) {
            throw new AgentRefusedException(
                'A page is named by a lowercase slug (letters, digits and dashes) and a two-letter language code.',
                'path_refused',
            );
        }

        return "pages/{$slug}.{$lang}.md";
    }

    /**
     * The path of a front-relative file as the linter names it, e.g. fronts/demo/pages/home.en.md.
     */
    public function repoPath(string $file): string
    {
        return $this->frontRoot().'/'.$this->editableFile($file);
    }

    /**
     * Creates a page. Refused when the page already exists or the site has no such language.
     *
     * @param  array{title: string, description: string, body?: ?string, nav_label?: ?string, cities?: list<string>, layout?: string, hero?: ?array<string, mixed>, forms?: list<string>}  $fields
     * @return array{path: string, saved: bool, lint: array{ok: bool, errors: list<array{rule: string, file: string, message: string}>, warnings: list<array{rule: string, file: string, message: string}>}}
     */
    public function createPage(string $slug, string $lang, array $fields, Actor $actor): array
    {
        $file = $this->pageFile($slug, $lang);
        $this->assertPageFields($fields);

        return $this->locked(function () use ($slug, $lang, $file, $fields, $actor): array {
            $this->ensureNewPage($slug, $lang);

            $frontmatter = $this->withPageFields($this->newPageFrontmatter($slug), $fields);

            return $this->withLint($this->writeFile($file, FrontMatter::compose($frontmatter, $this->body($fields['body'] ?? '')), $actor));
        });
    }

    /**
     * Saves a page's title, description, navigation label, cities and body, and its layout, hero and forms when given.
     * Every other frontmatter key (services, jsonld, keys the editor does not know) is kept.
     *
     * @param  array{title: string, description: string, body?: ?string, nav_label?: ?string, cities?: list<string>, layout?: string, hero?: ?array<string, mixed>, forms?: list<string>}  $fields
     * @return array{path: string, saved: bool, lint: array{ok: bool, errors: list<array{rule: string, file: string, message: string}>, warnings: list<array{rule: string, file: string, message: string}>}}
     */
    public function savePage(string $slug, string $lang, array $fields, Actor $actor): array
    {
        $file = $this->pageFile($slug, $lang);
        $this->assertPageFields($fields);

        return $this->locked(function () use ($slug, $lang, $file, $fields, $actor): array {
            $this->ensurePageExists($slug, $lang);

            $current = FrontMatter::parse((string) File::get($this->resolve($file)));
            $frontmatter = $this->withPageFields($current['frontmatter'], $fields);

            return $this->withLint($this->writeFile($file, FrontMatter::compose($frontmatter, $this->body($fields['body'] ?? '')), $actor));
        });
    }

    /**
     * Deletes a page. Home pages cannot be deleted: the front needs them. The deleted content stays in the history.
     *
     * @return array{path: string, saved: bool}
     */
    public function deletePage(string $slug, string $lang, Actor $actor): array
    {
        $file = $this->pageFile($slug, $lang);

        if ($slug === 'home') {
            throw new AgentRefusedException('The home page cannot be deleted.', 'invalid_params', 'Edit it instead.');
        }

        return $this->locked(function () use ($slug, $lang, $file, $actor): array {
            $this->ensurePageExists($slug, $lang);

            return $this->writeFile($file, null, $actor);
        });
    }

    /**
     * Writes the given facts into facts.yaml (see FACT_FIELDS). Keys the editor does not know are kept;
     * `discovery` is merged into the existing policy; an empty phone or email removes it.
     *
     * @param  array{name?: string, phone?: ?string, email?: ?string, cities?: list<string>, services?: list<array{slug: string, name: string|array<string, string>}>, languages?: list<string>, hours?: list<string>, never_write?: list<string>, discovery?: array{ai_search?: bool, ai_training?: bool}}  $changes
     * @return array{path: string, saved: bool, lint: array{ok: bool, errors: list<array{rule: string, file: string, message: string}>, warnings: list<array{rule: string, file: string, message: string}>}}
     */
    public function saveFacts(array $changes, Actor $actor): array
    {
        $changes = array_intersect_key($changes, array_flip(self::FACT_FIELDS));

        foreach (['phone', 'email'] as $optional) {
            if (array_key_exists($optional, $changes) && trim((string) $changes[$optional]) === '') {
                $changes[$optional] = null;
            }
        }

        if ($changes === []) {
            throw new AgentRefusedException('There are no facts to save.', 'invalid_params');
        }

        $this->assertContactFacts($changes);

        return $this->locked(fn (): array => $this->withLint($this->writeFile(self::FACTS_FILE, $this->mergeFacts($changes), $actor)));
    }

    /**
     * The same rules the Facts screen applies, so a save over MCP cannot write what the form would refuse.
     *
     * @param  array<string, mixed>  $changes
     */
    private function assertContactFacts(array $changes): void
    {
        $email = $changes['email'] ?? null;

        if ($email !== null && (! is_string($email) || mb_strlen($email) > 200 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
            throw new AgentRefusedException('email must be a valid email address.', 'invalid_params', 'For example office@example.com, or an empty string to remove it.');
        }

        $phone = $changes['phone'] ?? null;

        if ($phone !== null && (! is_string($phone) || mb_strlen($phone) > 50 || preg_match('/^[0-9+().\-\s]+(\s*(x|ext\.?)\s*\d+)?$/i', trim($phone)) !== 1)) {
            throw new AgentRefusedException('phone must be a phone number of at most 50 characters.', 'invalid_params', 'For example (239) 555-0100 or +1 239 555 0100.');
        }
    }

    /**
     * Writes new values for theme tokens that theme/tokens.css already declares. Values that could end the
     * declaration or load anything (; { } < > \ url( expression( @import, comments) are refused.
     *
     * @param  array<string, string>  $values  custom property name (with the leading --) => value
     * @return array{path: string, saved: bool, lint: array{ok: bool, errors: list<array{rule: string, file: string, message: string}>, warnings: list<array{rule: string, file: string, message: string}>}}
     */
    public function saveTheme(array $values, Actor $actor): array
    {
        if ($values === []) {
            throw new AgentRefusedException('There are no theme values to save.', 'invalid_params');
        }

        foreach ($values as $name => $value) {
            $problem = $this->tokens->problem((string) $value);

            if ($problem !== null) {
                throw new AgentRefusedException("{$name}: {$problem}", 'invalid_params', 'Use plain values such as #146b57, 1.25rem or Georgia, serif.');
            }
        }

        return $this->locked(function () use ($values, $actor): array {
            $css = $this->kit->theme($this->company());
            $this->ensureDeclared($values, $css);

            return $this->withLint($this->writeFile(self::THEME_FILE, $this->tokens->apply((string) $css, $values), $actor));
        });
    }

    /**
     * The company's component stylesheet (theme/components.css), or null when the front has none.
     */
    public function componentsCss(): ?string
    {
        return $this->kit->componentsCss($this->company());
    }

    /**
     * Replaces theme/components.css. Refused when the CSS could leave its <style> element or load anything but a
     * file under assets/ (FrontComponentsCss::problem).
     *
     * @return array{path: string, saved: bool, lint: array{ok: bool, errors: list<array{rule: string, file: string, message: string}>, warnings: list<array{rule: string, file: string, message: string}>}}
     */
    public function saveComponentsCss(string $css, Actor $actor): array
    {
        $css = str_replace(["\r\n", "\r"], "\n", $css);
        $problem = $this->componentsCss->problem($css);

        if ($problem !== null) {
            throw new AgentRefusedException($problem, 'invalid_params', 'Style the kit classes (front-card, front-card--featured…) with plain CSS; load images with url("assets/…").');
        }

        return $this->locked(fn (): array => $this->withLint($this->writeFile(self::COMPONENTS_CSS_FILE, rtrim($css)."\n", $actor)));
    }

    /**
     * Writes one file under assets/ (images, fonts, video, icons, the web manifest; no scripts) and commits it to the
     * content history. The content must match its extension and stay under ASSET_MAX_BYTES.
     *
     * @return array{path: string, saved: bool, url: ?string}
     */
    public function putAsset(string $path, string $content, Actor $actor): array
    {
        $relative = $this->assets->normalize($path);
        $extension = strtolower(pathinfo((string) $relative, PATHINFO_EXTENSION));

        if ($relative === null || ! in_array($extension, self::ASSET_TYPES, true)) {
            throw new AgentRefusedException(
                'An asset is a path under assets/ such as icons/robot.svg, of one of these types: '.implode(', ', self::ASSET_TYPES).'.',
                'path_refused',
            );
        }

        if ($content === '' || strlen($content) > self::ASSET_MAX_BYTES) {
            throw new AgentRefusedException('An asset must be between 1 byte and '.(self::ASSET_MAX_BYTES / 1_000_000).' MB.', 'invalid_params');
        }

        if (! $this->contentMatches($extension, $content)) {
            throw new AgentRefusedException("The content is not a .{$extension} file.", 'invalid_params', 'Send the file itself, base64-encoded.');
        }

        if ($extension === 'svg' && ($active = $this->svgActiveContent($content)) !== null) {
            throw new AgentRefusedException(
                "The SVG contains {$active}; an SVG asset may not carry scripts, on…= event attributes, javascript: URLs, foreignObject or entity declarations.",
                'invalid_params',
                'Export the SVG as plain shapes and paths (most editors have an "optimized" or "plain SVG" option).',
            );
        }

        $file = 'assets/'.$relative;

        return $this->locked(function () use ($file, $content, $actor): array {
            $absolute = $this->resolvePath($file);
            $path = $this->frontRoot().'/'.$file;

            if (is_file($absolute) && hash_file('sha256', $absolute) === hash('sha256', $content)) {
                return ['path' => $path, 'saved' => false, 'url' => $this->assetUrl($file)];
            }

            $note = (is_file($absolute) ? 'Replaced ' : 'Added ').$file;
            File::ensureDirectoryExists(dirname($absolute));
            $this->resolvePath($file);
            File::put($absolute, $content);
            $this->history->commit($this->company(), $note, $actor->label(), $file);

            return ['path' => $path, 'saved' => true, 'url' => $this->assetUrl($file)];
        });
    }

    /**
     * The front linter (the rules behind front:lint) on the live front.
     *
     * @return array{ok: bool, errors: list<array{rule: string, file: string, message: string}>, warnings: list<array{rule: string, file: string, message: string}>}
     */
    public function lint(): array
    {
        return $this->linter->lint($this->company());
    }

    /**
     * The errors and warnings of one file in a lint report (the path as the linter names it, e.g. fronts/demo/pages/home.en.md).
     *
     * @param  array{errors: list<array{rule: string, file: string, message: string}>, warnings: list<array{rule: string, file: string, message: string}>}  $report
     * @return array{errors: list<array{rule: string, file: string, message: string}>, warnings: list<array{rule: string, file: string, message: string}>}
     */
    public function lintFor(array $report, string $path): array
    {
        $ofFile = fn (array $issue): bool => $issue['file'] === $path;

        return [
            'errors' => array_values(array_filter($report['errors'], $ofFile)),
            'warnings' => array_values(array_filter($report['warnings'], $ofFile)),
        ];
    }

    /**
     * The live URL of the front root, or of one page.
     */
    public function liveUrl(?string $slug = null, ?string $lang = null): string
    {
        $site = new FrontSite($this->kit, $this->company());

        return $slug === null || $lang === null ? $site->rootUrl() : $site->url($slug, $lang);
    }

    /**
     * The saved versions of one front file, newest first (content null: the file was deleted then).
     *
     * @return list<FrontRevision>
     */
    public function history(string $file): array
    {
        return FrontRevision::query()
            ->where('company', $this->company())
            ->where('file', $this->editableFile($file))
            ->with('user')
            ->latest('id')
            ->limit(self::HISTORY_LIMIT)
            ->get()
            ->all();
    }

    /**
     * Writes a saved version of a front file back as the live file (a new revision).
     *
     * @return array{path: string, saved: bool, lint: array{ok: bool, errors: list<array{rule: string, file: string, message: string}>, warnings: list<array{rule: string, file: string, message: string}>}}
     */
    public function restore(string $file, int $revisionId, Actor $actor): array
    {
        $file = $this->editableFile($file);

        $revision = FrontRevision::query()
            ->where('company', $this->company())
            ->where('file', $file)
            ->find($revisionId);

        if ($revision === null) {
            throw new AgentRefusedException("That version is not in the history of {$file}.", 'not_found', 'Pick a version from the history list.');
        }

        if ($revision->content === null) {
            throw new AgentRefusedException("{$file} was deleted in that version.", 'not_found', 'Pick an earlier version.');
        }

        return $this->locked(fn (): array => $this->withLint($this->writeFile($file, $revision->content, $actor, "Restored version #{$revision->id}")));
    }

    /**
     * Writes (or, with null content, deletes) one front file and records the revision. Nothing is written or recorded
     * when the content is unchanged, and a page or UI-strings file that contains a never-write phrase is refused. Each
     * save is also a commit in the content folder's own git history (FrontContentHistory).
     *
     * @return array{path: string, saved: bool}
     */
    private function writeFile(string $file, ?string $content, Actor $actor, ?string $note = null): array
    {
        $absolute = $this->resolve($file);
        $path = $this->repoPath($file);
        $before = is_file($absolute) ? (string) File::get($absolute) : null;

        if ($before === $content) {
            return ['path' => $path, 'saved' => false];
        }

        $banned = $content === null ? [] : $this->linter->neverWriteIn($this->company(), $file, $content);

        if ($banned !== []) {
            throw new AgentRefusedException(
                'Not saved: '.implode(' ', array_slice($banned, 0, 3)),
                'invalid_params',
                'Reword it without the phrases listed under never_write in the facts.',
            );
        }

        $hasHistory = FrontRevision::query()->where('company', $this->company())->where('file', $file)->exists();

        if (! $hasHistory && $before !== null) {
            $this->record($file, $before, null, 'Before the first edit in Shopmade');
        }

        if ($content === null) {
            File::delete($absolute);
        } else {
            File::ensureDirectoryExists(dirname($absolute));
            $this->resolve($file);
            File::put($absolute, $content);
        }

        $note ??= $content === null ? 'Deleted' : ($before === null ? 'Created' : 'Edited');
        $this->record($file, $content, $actor, $note);
        $this->history->commit($this->company(), $note.' '.$file, $actor?->label(), $file);

        return ['path' => $path, 'saved' => true];
    }

    private function record(string $file, ?string $content, ?Actor $actor, string $note): void
    {
        FrontRevision::query()->create([
            'company' => $this->company(),
            'file' => $file,
            'content' => $content,
            'user_id' => $actor?->userModel()?->getKey(),
            'actor' => $actor?->label() ?? 'Shopmade',
            'via' => $actor?->via() ?? 'system',
            'note' => Str::limit($note, 250, ''),
        ]);
    }

    /**
     * The absolute path of a front file. Refuses anything that would resolve outside fronts/<company>: a symlinked
     * front folder, sub-folder or file.
     */
    private function resolve(string $file): string
    {
        return $this->resolvePath($this->editableFile($file));
    }

    /**
     * The absolute path of a validated front-relative file (see resolve()).
     */
    private function resolvePath(string $file): string
    {
        $frontDirectory = $this->kit->directory($this->company());

        if (is_link($frontDirectory)) {
            throw new AgentRefusedException("The front folder {$this->frontRoot()} is a symlink.", 'path_refused');
        }

        $front = realpath($frontDirectory);

        if ($front === false) {
            throw new AgentRefusedException("The front folder {$this->frontRoot()} is missing.", 'not_found');
        }

        $segments = explode('/', $file);
        $name = array_pop($segments);
        $directory = $front;

        foreach ($segments as $segment) {
            $directory .= '/'.$segment;

            if (is_link($directory) || (file_exists($directory) && ! is_dir($directory))) {
                throw new AgentRefusedException("{$this->frontRoot()}/{$segment} is not a plain folder.", 'path_refused');
            }
        }

        $absolute = $directory.'/'.$name;
        $parent = realpath($directory);

        if (is_link($absolute) || (file_exists($absolute) && ! is_file($absolute)) || ($parent !== false && ! str_starts_with($parent.'/', $front.'/'))) {
            throw new AgentRefusedException("{$this->frontRoot()}/{$file} is not a plain file inside the front.", 'path_refused');
        }

        return $absolute;
    }

    /**
     * Only these files are edited here: pages/<slug>.<lang>.md, facts.yaml, theme/tokens.css and theme/components.css
     * (assets go through putAsset()).
     */
    private function editableFile(string $file): string
    {
        $pattern = '/^(?:pages\/'.FrontKit::SLUG_PATTERN.'\.'.FrontKit::LANGUAGE_PATTERN.'\.md|facts\.yaml|theme\/tokens\.css|theme\/components\.css)\z/';

        if (preg_match($pattern, $file) !== 1) {
            throw new AgentRefusedException('The editor edits pages, facts.yaml, theme/tokens.css and theme/components.css only.', 'path_refused');
        }

        return $file;
    }

    /**
     * A new language version copies the layout and forms of the existing versions, so they stay in parity.
     *
     * @return array<string, mixed>
     */
    private function newPageFrontmatter(string $slug): array
    {
        $frontmatter = ['title' => '', 'description' => '', 'layout' => $slug === 'home' ? 'home' : 'page'];

        foreach ($this->pages() as $page) {
            if ($page['slug'] !== $slug) {
                continue;
            }

            foreach (['layout', 'forms'] as $key) {
                if (array_key_exists($key, $page['frontmatter'])) {
                    $frontmatter[$key] = $page['frontmatter'][$key];
                }
            }

            break;
        }

        return $frontmatter;
    }

    /**
     * A new page needs a language the site has, and a slug and language that are still free.
     */
    private function ensureNewPage(string $slug, string $lang): void
    {
        $languages = $this->languages();

        if (! in_array($lang, $languages, true)) {
            throw new AgentRefusedException(
                "The site has no {$lang} version.",
                'invalid_params',
                'Add the language under Website → Facts first (today: '.implode(', ', $languages).').',
            );
        }

        if ($this->page($slug, $lang) !== null) {
            throw new AgentRefusedException("The page {$slug} ({$lang}) already exists.", 'invalid_params', 'Open it from the page list to edit it.');
        }
    }

    private function ensurePageExists(string $slug, string $lang): void
    {
        if ($this->page($slug, $lang) === null) {
            throw new AgentRefusedException("There is no page {$slug} ({$lang}).", 'not_found', 'Create it from the page list.');
        }
    }

    /**
     * @param  array<string, mixed>  $frontmatter
     * @param  array{title: string, description: string, nav_label?: ?string, cities?: list<string>}  $fields
     * @return array<string, mixed>
     */
    private function withPageFields(array $frontmatter, array $fields): array
    {
        $frontmatter['title'] = trim($fields['title']);
        $frontmatter['description'] = trim($fields['description']);

        if (array_key_exists('nav_label', $fields)) {
            $label = trim((string) $fields['nav_label']);

            if ($label === '') {
                unset($frontmatter['nav_label']);
            } else {
                $frontmatter['nav_label'] = $label;
            }
        }

        if (array_key_exists('layout', $fields)) {
            $frontmatter['layout'] = $fields['layout'];
        }

        if (array_key_exists('hero', $fields)) {
            $hero = array_filter((array) $fields['hero'], fn (mixed $value): bool => is_string($value) && trim($value) !== '');

            if ($hero === []) {
                unset($frontmatter['hero']);
            } else {
                $frontmatter['hero'] = array_map('trim', $hero);
            }
        }

        if (array_key_exists('forms', $fields)) {
            $forms = array_values(array_unique($fields['forms']));

            if ($forms === []) {
                unset($frontmatter['forms']);
            } else {
                $frontmatter['forms'] = $forms;
            }
        }

        if (array_key_exists('cities', $fields)) {
            $cities = array_values(array_unique(array_filter(array_map('trim', $fields['cities']), fn (string $city): bool => $city !== '')));

            if ($cities === []) {
                unset($frontmatter['cities']);
            } else {
                $frontmatter['cities'] = $cities;
            }
        }

        return $frontmatter;
    }

    /**
     * The layout, hero and forms a save may set: a known layout; hero keys image/poster/mp4/webm naming files under
     * assets/ of the right kind, alt/kicker/caption text and position "x% y%"; forms that are slugs in facts.forms.
     *
     * @param  array<string, mixed>  $fields
     */
    private function assertPageFields(array $fields): void
    {
        if (array_key_exists('layout', $fields) && ! in_array($fields['layout'], FrontSite::LAYOUTS, true)) {
            throw new AgentRefusedException('layout must be one of: '.implode(', ', FrontSite::LAYOUTS).'.', 'invalid_params');
        }

        if (array_key_exists('hero', $fields) && $fields['hero'] !== null) {
            $hero = $fields['hero'];
            $allowed = ['image', 'poster', 'mp4', 'webm', 'alt', 'kicker', 'caption', 'position'];

            if (! is_array($hero) || array_diff(array_keys($hero), $allowed) !== []) {
                throw new AgentRefusedException('hero takes only: '.implode(', ', $allowed).'.', 'invalid_params');
            }

            foreach ($hero as $key => $value) {
                if ($value !== null && (! is_string($value) || mb_strlen($value) > 300)) {
                    throw new AgentRefusedException("hero.{$key} must be text of at most 300 characters.", 'invalid_params');
                }
            }

            $kinds = ['image' => FrontAssets::IMAGE_TYPES, 'poster' => FrontAssets::IMAGE_TYPES, 'mp4' => ['mp4'], 'webm' => ['webm']];

            foreach ($kinds as $key => $extensions) {
                $value = trim((string) ($hero[$key] ?? ''));

                if ($value === '') {
                    continue;
                }

                $relative = $this->assets->normalize($value);

                if ($relative === null || ! in_array(strtolower(pathinfo($relative, PATHINFO_EXTENSION)), $extensions, true)) {
                    throw new AgentRefusedException("hero.{$key} must name a .".implode('/.', $extensions).' file under assets/.', 'invalid_params', 'Upload it with asset_put first, then use assets/<path>.');
                }

                if ($this->assets->resolve($this->company(), $relative) === null) {
                    throw new AgentRefusedException("hero.{$key}: assets/{$relative} does not exist.", 'not_found', 'Upload it with asset_put first.');
                }
            }

            $position = trim((string) ($hero['position'] ?? ''));

            if ($position !== '' && preg_match(FrontSite::HERO_POSITION, $position) !== 1) {
                throw new AgentRefusedException('hero.position must look like "50% 30%".', 'invalid_params');
            }
        }

        if (array_key_exists('forms', $fields)) {
            $declared = array_keys((new FrontSite($this->kit, $this->company()))->declaredForms());

            if (! is_array($fields['forms'])) {
                throw new AgentRefusedException('forms must be a list of form slugs.', 'invalid_params');
            }

            foreach ($fields['forms'] as $form) {
                if (! is_string($form) || ! in_array($form, $declared, true)) {
                    throw new AgentRefusedException(
                        sprintf('Form "%s" is not declared in facts.forms.', is_string($form) ? $form : gettype($form)),
                        'invalid_params',
                        $declared === [] ? 'Declare the form under forms: in facts.yaml first.' : 'Declared forms: '.implode(', ', $declared).'.',
                    );
                }
            }
        }
    }

    /**
     * Whether the bytes look like the type their extension names.
     */
    private function contentMatches(string $extension, string $content): bool
    {
        return match ($extension) {
            'svg' => preg_match('/^\s*(?:<\?xml[^>]*>\s*)?(?:<!--.*?-->\s*)*(?:<!DOCTYPE[^>]*>\s*)?<svg[\s>]/is', $content) === 1,
            'webmanifest' => is_array(json_decode($content, true)),
            'woff2' => str_starts_with($content, 'wOF2'),
            'ico' => str_starts_with($content, "\x00\x00\x01\x00"),
            'webm' => str_starts_with($content, "\x1A\x45\xDF\xA3"),
            'mp4', 'avif' => substr($content, 4, 4) === 'ftyp',
            default => in_array((new \finfo(FILEINFO_MIME_TYPE))->buffer($content), match ($extension) {
                'png' => ['image/png'],
                'jpg', 'jpeg' => ['image/jpeg'],
                'webp' => ['image/webp'],
                'gif' => ['image/gif'],
                default => [],
            }, true),
        };
    }

    /**
     * What makes the SVG able to run code when opened, or null when it is plain drawing. Checked on the raw bytes and
     * again with character references decoded, since attribute values like &#106;avascript: are decoded by the parser.
     */
    private function svgActiveContent(string $svg): ?string
    {
        $gap = '[\s\x00-\x1f]*';
        $javascript = '/'.implode($gap, str_split('javascript')).$gap.':/i';
        $checks = [
            'a <script> element' => '/<\s*(?:[\w.-]+:)?script\b/i',
            'a <foreignObject> element' => '/<\s*(?:[\w.-]+:)?foreignObject\b/i',
            'an on…= event attribute' => '/[\s"\'\/](?:[\w.-]+:)?on[a-z]+\s*=/i',
            'a javascript: URL' => $javascript,
            'an entity declaration' => '/<!ENTITY\b/i',
        ];
        $decoded = html_entity_decode($svg, ENT_QUOTES | ENT_XML1 | ENT_HTML5, 'UTF-8');

        foreach ($checks as $label => $pattern) {
            if (preg_match($pattern, $svg) === 1 || preg_match($pattern, $decoded) === 1) {
                return $label;
            }
        }

        return null;
    }

    private function assetUrl(string $file): ?string
    {
        return (new FrontSite($this->kit, $this->company()))->assetUrl($file);
    }

    private function body(?string $body): string
    {
        return trim(str_replace(["\r\n", "\r"], "\n", (string) $body), "\n");
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function mergeFacts(array $changes): string
    {
        try {
            return $this->factsYaml->merge($this->kit->factsYaml($this->company()), $changes);
        } catch (ParseException $exception) {
            throw new AgentRefusedException(
                'facts.yaml cannot be updated: '.$exception->getMessage(),
                'invalid_state',
                'Restore an earlier version of facts.yaml under Website → Facts.',
            );
        }
    }

    /**
     * @param  array<string, string>  $values
     */
    private function ensureDeclared(array $values, ?string $css): void
    {
        if ($css === null) {
            throw new AgentRefusedException('This front has no theme/tokens.css.', 'not_found', 'Add theme/tokens.css to the front first.');
        }

        $unknown = array_keys(array_diff_key($values, $this->tokens->parse($css)));

        if ($unknown !== []) {
            throw new AgentRefusedException(
                'Unknown theme '.Str::plural('token', count($unknown)).': '.implode(', ', array_slice($unknown, 0, 5)).'.',
                'invalid_params',
                'Only tokens that theme/tokens.css already declares can be edited here.',
            );
        }
    }

    /**
     * @param  array{path: string, saved: bool}  $result
     * @return array{path: string, saved: bool, lint: array{ok: bool, errors: list<array{rule: string, file: string, message: string}>, warnings: list<array{rule: string, file: string, message: string}>}}
     */
    private function withLint(array $result): array
    {
        return [...$result, 'lint' => $this->lint()];
    }

    /**
     * Runs the callback under the company's editor lock, so two saves never write the same file at once.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    private function locked(Closure $callback): mixed
    {
        try {
            return Cache::lock('front-editor:'.$this->company(), self::LOCK_SECONDS)->block(self::LOCK_WAIT_SECONDS, $callback);
        } catch (LockTimeoutException) {
            throw new AgentRefusedException('Another change to the website is being saved.', 'concurrency', 'Try again in a few seconds.');
        }
    }

    private function frontRoot(): string
    {
        return trim((string) config('agents.paths.fronts', 'fronts'), '/').'/'.$this->company();
    }
}
