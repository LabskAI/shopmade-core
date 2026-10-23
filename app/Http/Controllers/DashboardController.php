<?php

namespace App\Http\Controllers;

use App\Core\Front\FrontEditor;
use App\Core\Packages\Hooks;
use App\Models\Customer;
use App\Models\CustomerChangeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class DashboardController extends Controller
{
    public function __invoke(Request $request, Hooks $hooks, FrontEditor $editor): View|RedirectResponse
    {
        $user = $request->user();

        if ($user?->isClient()) {
            return redirect()->route('portal.home');
        }

        try {
            $pageCount = count($editor->pages());
        } catch (Throwable) {
            $pageCount = 0;
        }

        return view('dashboard', [
            'customerCount' => Customer::query()->count(),
            'requestCount' => CustomerChangeRequest::query()->where('created_at', '>=', now()->subDays(7))->count(),
            'pageCount' => $pageCount,
            'cards' => $hooks->dashboardCards($user),
        ]);
    }
}
