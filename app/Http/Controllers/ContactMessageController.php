<?php

namespace App\Http\Controllers;

use App\Core\Front\FrontSite;
use App\Models\ContactMessage;
use Illuminate\View\View;

class ContactMessageController extends Controller
{
    /**
     * Website contact form messages, newest first. Opening the list marks the shown messages as read; the page still flags them as new this once.
     */
    public function index(FrontSite $site): View
    {
        $messages = ContactMessage::query()
            ->where('company', $site->company())
            ->latest()
            ->orderByDesc('id')
            ->paginate(20);

        ContactMessage::query()
            ->whereKey($messages->getCollection()->modelKeys())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return view('contact-messages.index', compact('messages'));
    }
}
