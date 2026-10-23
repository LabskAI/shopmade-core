<?php

namespace App\Http\Controllers\Portal;

use App\Core\Packages\Hooks;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortalHomeController extends Controller
{
    public function __invoke(Request $request, Hooks $hooks): View
    {
        return view('portal.home', [
            'customers' => $request->user()->customers()->orderBy('name')->get(),
            'sections' => $hooks->portalSections($request->user()),
        ]);
    }
}
