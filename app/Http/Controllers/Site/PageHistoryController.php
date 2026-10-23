<?php

namespace App\Http\Controllers\Site;

use App\Core\Agents\Actor;
use App\Core\Agents\AgentRefusedException;
use App\Core\Front\FrontEditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PageHistoryController extends FrontEditorController
{
    /**
     * The saved versions of a page, newest first.
     */
    public function index(string $lang, string $slug, FrontEditor $editor): View
    {
        $page = $editor->page($slug, $lang);
        $versions = $editor->history($editor->pageFile($slug, $lang));

        abort_if($page === null && $versions === [], 404);

        return view('site.pages.history', [
            'slug' => $slug,
            'lang' => $lang,
            'page' => $page,
            'versions' => $versions,
        ]);
    }

    /**
     * Restores a saved version: it is live right away, as a new version.
     */
    public function store(Request $request, string $lang, string $slug, FrontEditor $editor): RedirectResponse
    {
        $data = $request->validate([
            'revision' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $result = $editor->restore($editor->pageFile($slug, $lang), (int) $data['revision'], Actor::forUser($request->user()));
        } catch (AgentRefusedException $exception) {
            return back()->withErrors(['revision' => $this->refusal($exception)]);
        }

        return redirect()->route('site.pages.edit', ['lang' => $lang, 'slug' => $slug])
            ->with('status', $result['saved']
                ? "Restored version #{$data['revision']}. It is live."
                : 'The page already has that version.');
    }
}
