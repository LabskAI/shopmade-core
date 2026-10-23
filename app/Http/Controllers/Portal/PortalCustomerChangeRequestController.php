<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerChangeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PortalCustomerChangeRequestController extends Controller
{
    public function store(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('create', [CustomerChangeRequest::class, $customer]);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        CustomerChangeRequest::query()->create([
            'customer_id' => $customer->id,
            'user_id' => $request->user()->id,
            'message' => $data['message'],
        ]);

        return redirect()->route('portal.home')->with('status', 'Request sent. We will follow up.');
    }
}
