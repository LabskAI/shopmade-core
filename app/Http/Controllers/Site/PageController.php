<?php

namespace App\Http\Controllers\Site;

use App\Core\Agents\Actor;
use App\Core\Agents\AgentRefusedException;
use App\Core\Front\FrontEditor;
use App\Core\Front\FrontKit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PageController extends FrontEditorController
{
    public function index(FrontEditor $editor): View
    {

        return view('site.pages.index', [
            'pages' => collect($editor->pages())->groupBy('slug'),
            'languages' => $editor->languages(),
        ]);
    }

    /**
     * `?slug=services&lang=es` starts a new language version of an existing page, prefilled from another version.
     */
    public function create(Request $request, FrontEditor $editor): View
    {
        $languages = $editor->languages();
        $slug = $request->string('slug')->toString();
        $slug = preg_match('/^'.FrontKit::SLUG_PATTERN.'\z/', $slug) === 1 ? $slug : '';
        $lang = in_array($request->string('lang')->toString(), $languages, true) ? $request->string('lang')->toString() : $languages[0];

        $source = $slug === '' ? null : collect($editor->pages())->firstWhere('slug', $slug);

        return view('site.pages.create', [
            'languages' => $languages,
            'slug' => $slug,
            'lang' => $lang,
            'source' => $source,
            'cities' => $this->cities($editor->facts()),
        ]);
    }

    public function store(Request $request, FrontEditor $editor): RedirectResponse
    {
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:80', 'regex:/^'.FrontKit::SLUG_PATTERN.'\z/'],
            'lang' => ['required', 'string', Rule::in($editor->languages())],
            ...$this->pageRules(),
        ], [
            'slug.regex' => 'Use lowercase letters, digits and dashes, for example about-us.',
            'lang.in' => 'Pick one of the site languages.',
        ]);

        try {
            $result = $editor->createPage($data['slug'], $data['lang'], $this->pageFields($data), Actor::forUser($request->user()));
        } catch (AgentRefusedException $exception) {
            return back()->withInput()->withErrors(['page' => $this->refusal($exception)]);
        }

        return redirect()->route('site.pages.edit', ['lang' => $data['lang'], 'slug' => $data['slug']])
            ->with('status', $this->savedMessage($result));
    }

    public function edit(string $lang, string $slug, FrontEditor $editor): View
    {
        $page = $editor->page($slug, $lang);

        abort_if($page === null, 404);

        $lint = $editor->lint();
        $file = $editor->pageFile($slug, $lang);

        return view('site.pages.edit', [
            'page' => $page,
            'lint' => $editor->lintFor($lint, $editor->repoPath($file)),
            'liveUrl' => $editor->liveUrl($slug, $lang),
            'translations' => collect($editor->languages())
                ->mapWithKeys(fn (string $language): array => [$language => $editor->page($slug, $language) !== null])
                ->all(),
            'cities' => $this->cities($editor->facts()),
        ]);
    }

    public function update(Request $request, string $lang, string $slug, FrontEditor $editor): RedirectResponse
    {
        $data = $request->validate($this->pageRules());

        try {
            $result = $editor->savePage($slug, $lang, $this->pageFields($data), Actor::forUser($request->user()));
        } catch (AgentRefusedException $exception) {
            return back()->withInput()->withErrors(['page' => $this->refusal($exception)]);
        }

        return redirect()->route('site.pages.edit', ['lang' => $lang, 'slug' => $slug])
            ->with('status', $this->savedMessage($result));
    }

    public function destroy(Request $request, string $lang, string $slug, FrontEditor $editor): RedirectResponse
    {
        try {
            $editor->deletePage($slug, $lang, Actor::forUser($request->user()));
        } catch (AgentRefusedException $exception) {
            return back()->withErrors(['page' => $this->refusal($exception)]);
        }

        return redirect()->route('site.pages.index')
            ->with('status', "Page {$slug} ({$lang}) deleted. Its last version stays in the history.");
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function pageRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:500'],
            'nav_label' => ['nullable', 'string', 'max:80'],
            'cities' => ['nullable', 'string', 'max:2000'],
            'body' => ['nullable', 'string', 'max:100000'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{title: string, description: string, nav_label: ?string, cities: list<string>, body: string}
     */
    private function pageFields(array $data): array
    {
        return [
            'title' => (string) $data['title'],
            'description' => (string) $data['description'],
            'nav_label' => $data['nav_label'] ?? null,
            'cities' => array_values(array_filter(
                array_map('trim', preg_split('/[,\n]+/', (string) ($data['cities'] ?? '')) ?: []),
                fn (string $city): bool => $city !== '',
            )),
            'body' => (string) ($data['body'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $facts
     * @return list<string>
     */
    private function cities(array $facts): array
    {
        return array_values(array_filter((array) ($facts['cities'] ?? []), 'is_string'));
    }
}
