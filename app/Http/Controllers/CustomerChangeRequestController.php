<?php

namespace App\Http\Controllers;

use App\Models\CustomerChangeRequest;
use Illuminate\View\View;

class CustomerChangeRequestController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', CustomerChangeRequest::class);

        $requests = CustomerChangeRequest::query()
            ->with(['customer', 'user'])
            ->latest()
            ->orderByDesc('id')
            ->paginate(20);

        return view('customer-change-requests.index', compact('requests'));
    }
}
