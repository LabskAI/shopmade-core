<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PluginStatus;
use App\Http\Controllers\Controller;
use App\Models\Plugin;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PluginAdminController extends Controller
{
    public function index(): View
    {
        $plugins = Plugin::query()
            ->orderBy('name')
            ->get();

        return view('admin.plugins.index', compact('plugins'));
    }

    public function disable(Plugin $plugin): RedirectResponse
    {
        if ($plugin->status === PluginStatus::Removed) {
            return back()->withErrors(['plugin' => "{$plugin->name} is no longer installed."]);
        }

        $plugin->update(['status' => PluginStatus::Disabled]);

        return back()->with('status', "{$plugin->name} disabled.");
    }

    public function enable(Plugin $plugin): RedirectResponse
    {
        if ($plugin->status === PluginStatus::Removed) {
            return back()->withErrors(['plugin' => "{$plugin->name} is no longer installed."]);
        }

        $plugin->update(['status' => PluginStatus::Active]);

        return back()->with('status', "{$plugin->name} re-enabled.");
    }
}
