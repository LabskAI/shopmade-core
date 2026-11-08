<?php

use App\Core\Front\FrontContactForm;
use App\Mail\ContactMessageMail;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Agents\Front\FrontFixture;

afterEach(fn () => FrontFixture::cleanup());

function contactFront(array $form = []): void
{
    FrontFixture::create([
        'facts.yaml' => FrontFixture::facts([
            'forms' => [['slug' => 'contact', 'name' => 'Contact us', 'provider' => 'shopmade', 'status' => 'live', ...$form]],
        ]),
        'pages/services.en.md' => FrontFixture::page(
            ['title' => 'Cleaning services', 'description' => 'House cleaning.', 'forms' => ['contact']],
            '# Cleaning services',
        ),
    ]);
}

function contactPost(array $fields = []): array
{
    return [
        'name' => 'Ana Lopez',
        'email' => 'ana@example.com',
        'phone' => '555-0101',
        'message' => 'Can you clean on Friday?',
        'lang' => 'en',
        'page' => url('/site/services'),
        'token' => Crypt::encryptString((string) now()->subMinute()->getTimestamp()),
        ...$fields,
    ];
}

it('renders the built-in form with no script', function () {
    contactFront();

    $html = $this->get(route('front.page', 'services'))->assertOk()->getContent();

    expect($html)->toContain('action="'.url('/').'/site/forms/contact"')
        ->toContain('name="'.FrontContactForm::HONEYPOT.'"')
        ->toContain('name="message"')
        ->not->toContain('data-aiformflow')
        ->not->toContain('form.js');
});

it('stores the message, emails the site and sends the visitor back to the page', function () {
    Mail::fake();
    contactFront();

    $this->post(route('front.form', 'contact'), contactPost())
        ->assertRedirect(url('/site/services').'?form=contact&status=sent#form-contact');

    $message = ContactMessage::query()->sole();
    expect($message->email)->toBe('ana@example.com')
        ->and($message->company)->toBe('acme')
        ->and($message->page_url)->toBe(url('/site/services'));

    Mail::assertSent(ContactMessageMail::class, fn (ContactMessageMail $mail): bool => $mail->hasTo('hello@acme.example') && $mail->hasReplyTo('ana@example.com'));

    $this->get(url('/site/services').'?form=contact&status=sent')->assertSee('Thanks, we got your message.', false);
});

it('drops bot posts without storing them', function (array $fields) {
    Mail::fake();
    contactFront();

    $this->post(route('front.form', 'contact'), contactPost($fields))->assertRedirect();

    expect(ContactMessage::query()->count())->toBe(0);
    Mail::assertNothingSent();
})->with([
    'honeypot filled' => [fn (): array => [FrontContactForm::HONEYPOT => 'http://spam.example']],
    'no token' => [fn (): array => ['token' => '']],
    'too fast' => [fn (): array => ['token' => Crypt::encryptString((string) now()->getTimestamp())]],
    'forged token' => [fn (): array => ['token' => 'abc']],
]);

it('sends the visitor back with invalid when a field is missing', function () {
    contactFront();

    $this->post(route('front.form', 'contact'), contactPost(['email' => 'not-an-email']))
        ->assertRedirect(url('/site/services').'?form=contact&status=invalid#form-contact');

    expect(ContactMessage::query()->count())->toBe(0);
});

it('never redirects off the site', function () {
    contactFront();

    $this->post(route('front.form', 'contact'), contactPost(['page' => 'https://evil.example/x']))
        ->assertRedirect(url('/site').'?form=contact&status=sent#form-contact');
});

it('refuses forms that are not built-in or not live', function (array $form) {
    contactFront($form);

    $this->post(route('front.form', 'contact'), contactPost())->assertNotFound();
})->with([
    'aiformflow' => [['provider' => null, 'client' => 'acme']],
    'draft' => [['status' => 'draft']],
]);

it('lists messages for staff and keeps them from clients', function () {
    contactFront();
    ContactMessage::factory()->create(['company' => 'acme', 'name' => 'Ana Lopez']);

    $this->actingAs(User::factory()->create())
        ->get(route('contact-messages.index'))
        ->assertOk()
        ->assertSee('Ana Lopez')
        ->assertSee('New');

    expect(ContactMessage::query()->sole()->isRead())->toBeTrue();

    $this->actingAs(User::factory()->client()->create())
        ->get(route('contact-messages.index'))
        ->assertForbidden();
});
