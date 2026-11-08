<?php

namespace App\Http\Controllers\Front;

use App\Core\Front\FrontContactForm;
use App\Core\Front\FrontSite;
use App\Enums\UserRole;
use App\Mail\ContactMessageMail;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

/**
 * Receives the built-in contact form (facts.forms entry with `provider: shopmade`). Front pages are stateless, so
 * the answer is a redirect back to the page with ?form=<slug>&status=sent|invalid, which the page shows.
 */
class ContactFormController extends FrontController
{
    public function store(Request $request, FrontSite $site, string $form): RedirectResponse
    {
        $declared = $site->declaredForms()[$form] ?? null;

        abort_if($declared === null || ($site->form($form, $site->defaultLanguage())['builtin'] ?? false) !== true, 404);

        $back = $this->backUrl($site, $request->input('page'));

        if (filled($request->input(FrontContactForm::HONEYPOT)) || ! FrontContactForm::tokenIsFresh($request->input('token'))) {
            return $this->answer($back, $form, 'sent');
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'message' => ['required', 'string', 'max:5000'],
            'lang' => ['nullable', 'string', 'size:2'],
        ]);

        if ($validator->fails()) {
            return $this->answer($back, $form, 'invalid');
        }

        $data = $validator->validated();
        $lang = isset($data['lang']) && $site->hasLanguage($data['lang']) ? $data['lang'] : $site->defaultLanguage();

        $contactMessage = ContactMessage::query()->create([
            'company' => $site->company(),
            'form' => $form,
            'name' => trim($data['name']),
            'email' => trim($data['email']),
            'phone' => filled($data['phone'] ?? null) ? trim($data['phone']) : null,
            'message' => trim($data['message']),
            'page_url' => Str::startsWith($back, $site->rootUrl()) ? $back : null,
            'lang' => $lang,
        ]);

        $this->notify($site, $contactMessage);

        return $this->answer($back, $form, 'sent');
    }

    /**
     * Email the site's facts.email, else every super admin. A mail failure never loses the stored message.
     */
    private function notify(FrontSite $site, ContactMessage $contactMessage): void
    {
        $recipients = filled($site->email())
            ? [$site->email()]
            : User::query()->where('role', UserRole::SuperAdmin)->whereNull('deactivated_at')->pluck('email')->all();

        if ($recipients === []) {
            return;
        }

        try {
            Mail::to($recipients)->send(new ContactMessageMail($contactMessage, $site->name()));
        } catch (Throwable $exception) {
            Log::warning('Contact form email failed: '.$exception->getMessage(), ['contact_message_id' => $contactMessage->id]);
        }
    }

    /**
     * The page the form was posted from when it belongs to this site, else the site's home page.
     */
    private function backUrl(FrontSite $site, mixed $page): string
    {
        $root = $site->rootUrl();

        if (is_string($page) && ($page === $root || Str::startsWith($page, $root.'/')) && preg_match('/[\s"\'<>?#]/', $page) !== 1) {
            return $page;
        }

        return $site->url('home', $site->defaultLanguage());
    }

    private function answer(string $back, string $form, string $status): RedirectResponse
    {
        return redirect()->to($back.'?'.http_build_query(['form' => $form, 'status' => $status]).'#form-'.$form, 303);
    }
}
