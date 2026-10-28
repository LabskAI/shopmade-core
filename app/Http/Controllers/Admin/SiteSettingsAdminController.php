<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SiteSettingsAdminController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.edit', [
            'orgName' => Setting::get('org.name', config('app.org_name')),
            'orgLogo' => Setting::get('org.logo'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'org_name' => ['required', 'string', 'max:255'],
            'org_logo' => ['nullable', 'string', 'max:255'],
        ]);

        Setting::put('org.name', $validated['org_name']);

        if (filled($validated['org_logo'] ?? null)) {
            Setting::put('org.logo', $validated['org_logo']);
        } else {
            Setting::query()->whereKey('org.logo')->delete();
        }

        return redirect()->route('admin.settings.edit')->with('status', 'Settings saved.');
    }
}
