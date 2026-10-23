<?php

namespace Plugins\CustomerNotes\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Plugins\CustomerNotes\Models\CustomerNote;

class CustomerNoteController
{
    public function index(): View
    {
        $notes = CustomerNote::query()
            ->with('customer')
            ->latest()
            ->paginate(20);

        return view('customer-notes::index', compact('notes'));
    }

    public function customer(Customer $customer): View
    {
        $note = CustomerNote::query()->firstOrNew(['customer_id' => $customer->id]);

        return view('customer-notes::customer', compact('customer', 'note'));
    }

    public function store(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string'],
        ]);

        CustomerNote::query()->updateOrCreate(
            ['customer_id' => $customer->id],
            ['body' => $data['body']],
        );

        return redirect()->route('customer-notes.customer', $customer)->with('status', 'Note saved.');
    }
}
