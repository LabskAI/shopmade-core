<?php

namespace App\Http\Controllers\Site;

use App\Core\Front\FrontEditor;
use App\Core\Front\FrontReadiness;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class DiscoveryCheckController extends Controller
{
    /**
     * Runs the agent-readiness checks against the live front.
     */
    public function store(FrontEditor $editor, FrontReadiness $readiness): RedirectResponse
    {
        $url = $editor->liveUrl();
        $report = $readiness->check($url, $editor->company());

        return redirect()->route('site.discovery.show')->with('readiness', [
            'url' => $url,
            'ran_at' => now()->toIso8601String(),
            'ok' => $report['ok'],
            'checks' => $report['checks'],
        ]);
    }
}
