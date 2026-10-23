<?php

namespace App\Http\Controllers\Front;

use App\Core\Front\FrontJsonLd;
use App\Core\Front\FrontSite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PageController extends FrontController
{
    public function home(Request $request, FrontSite $site, FrontJsonLd $jsonLd): Response
    {
        return $this->render($request, $site, $jsonLd, 'home', $site->defaultLanguage());
    }

    /**
     * /{lang} is a language home; a two-letter segment that is not a site language is a page slug.
     */
    public function language(Request $request, FrontSite $site, FrontJsonLd $jsonLd, string $lang): Response
    {
        if (! $site->hasLanguage($lang)) {
            return $this->render($request, $site, $jsonLd, $lang, $site->defaultLanguage());
        }

        if ($lang === $site->defaultLanguage()) {
            return $this->canonical($request, $site, 'home', $lang);
        }

        return $this->render($request, $site, $jsonLd, 'home', $lang);
    }

    public function show(Request $request, FrontSite $site, FrontJsonLd $jsonLd, string $slug): Response
    {
        if ($slug === 'home') {
            return $this->canonical($request, $site, 'home', $site->defaultLanguage());
        }

        return $this->render($request, $site, $jsonLd, $slug, $site->defaultLanguage());
    }

    public function localized(Request $request, FrontSite $site, FrontJsonLd $jsonLd, string $lang, string $slug): Response
    {
        abort_unless($site->hasLanguage($lang), 404);

        if ($lang === $site->defaultLanguage() || $slug === 'home') {
            abort_if($site->page($slug, $lang) === null, 404);

            return $this->canonical($request, $site, $slug, $lang);
        }

        return $this->render($request, $site, $jsonLd, $slug, $lang);
    }

    /**
     * The HTML page, or its markdown source for clients that prefer it. A `noindex` page sends
     * `X-Robots-Tag: noindex` with both, because the markdown has no robots meta tag.
     */
    private function render(Request $request, FrontSite $site, FrontJsonLd $jsonLd, string $slug, string $lang): Response
    {
        $page = $site->page($slug, $lang);

        abort_if($page === null, 404);

        $headers = ['Vary' => 'Accept'];

        if (! $site->isIndexable($page)) {
            $headers['X-Robots-Tag'] = 'noindex';
        }

        if ($request->prefers(['text/html', 'text/markdown']) === 'text/markdown') {
            return response($site->markdown($page), 200, [
                'Content-Type' => 'text/markdown; charset=UTF-8',
                ...$headers,
            ]);
        }

        return response()->view('front.page', [
            ...$site->viewData($page),
            'jsonLd' => $jsonLd->scripts($site, $page),
        ], 200, [
            ...$headers,
            'Link' => $site->linkHeader(),
        ]);
    }

    /**
     * Default-language and /home URLs redirect to the one canonical URL, keeping the query (signed previews).
     */
    private function canonical(Request $request, FrontSite $site, string $slug, string $lang): RedirectResponse
    {
        $query = $request->getQueryString();

        return redirect()->to($site->url($slug, $lang).($query !== null ? '?'.$query : ''), 301);
    }
}
