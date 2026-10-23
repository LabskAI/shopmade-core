<?php

namespace App\Http\Controllers\Site;

use App\Core\Agents\Actor;
use App\Core\Agents\AgentRefusedException;
use App\Core\Front\FrontDiscovery;
use App\Core\Front\FrontEditor;
use App\Core\Front\FrontKit;
use App\Core\Front\FrontSite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DiscoveryController extends FrontEditorController
{
    /**
     * The AI crawler policy (facts.discovery) and what the live front publishes now.
     */
    public function show(FrontEditor $editor, FrontKit $kit, FrontDiscovery $discovery): View
    {
        $facts = $editor->facts();
        $live = new FrontSite($kit, $editor->company());

        return view('site.discovery.show', [
            'aiSearch' => ($facts['discovery']['ai_search'] ?? true) !== false,
            'aiTraining' => ($facts['discovery']['ai_training'] ?? false) === true,
            'liveRobots' => $live->exists() ? $discovery->robots($live) : null,
            'liveFiles' => collect(['robots.txt', 'sitemap.xml', 'llms.txt', 'llms-full.txt'])
                ->mapWithKeys(fn (string $file): array => [$file => $live->fileUrl($file)])
                ->all(),
            'liveUrl' => $live->rootUrl(),
            'readiness' => session('readiness'),
        ]);
    }

    public function update(Request $request, FrontEditor $editor): RedirectResponse
    {
        $request->validate([
            'ai_search' => ['nullable', 'boolean'],
            'ai_training' => ['nullable', 'boolean'],
        ]);

        try {
            $result = $editor->saveFacts([
                'discovery' => [
                    'ai_search' => $request->boolean('ai_search'),
                    'ai_training' => $request->boolean('ai_training'),
                ],
            ], Actor::forUser($request->user()));
        } catch (AgentRefusedException $exception) {
            return back()->withErrors(['discovery' => $this->refusal($exception)]);
        }

        return redirect()->route('site.discovery.show')->with('status', $this->savedMessage($result, wholeFront: true));
    }
}
