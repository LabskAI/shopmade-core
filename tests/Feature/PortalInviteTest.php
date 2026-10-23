<?php

use App\Enums\UserRole;
use App\Mail\PortalAccessGrantedMail;
use App\Mail\PortalInviteMail;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

it('shows the grant portal access form on the customer page', function () {
    $admin = User::factory()->admin()->create();
    $customer = Customer::factory()->create();

    $this->actingAs($admin)
        ->get(route('customers.show', $customer))
        ->assertOk()
        ->assertSee('Client portal access')
        ->assertSee('Grant portal access')
        ->assertSee('set-password link');
});

it('forbids users without users.manage from granting portal access', function () {
    $customer = Customer::factory()->create();
    $user = User::factory()->user()->create();

    $this->actingAs($user)
        ->post(route('customers.portal-users.store', $customer), [
            'email' => 'client@example.com',
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('users', ['email' => 'client@example.com']);
});

it('sends a signed set-password invite when granting a new email', function () {
    Mail::fake();
    $this->freezeTime();

    $admin = User::factory()->admin()->create();
    $customer = Customer::factory()->create(['name' => 'Harbor Offices']);

    $this->actingAs($admin)
        ->post(route('customers.portal-users.store', $customer), [
            'name' => 'Pat Client',
            'email' => 'pat@example.com',
        ])
        ->assertRedirect(route('customers.show', $customer))
        ->assertSessionHas('status', 'Portal invite sent. The set-password link expires in 72 hours.');

    $client = User::query()->where('email', 'pat@example.com')->first();

    expect($client)->not->toBeNull()
        ->and($client->name)->toBe('Pat Client')
        ->and($client->role)->toBe(UserRole::Client)
        ->and($client->mustSetPassword())->toBeTrue()
        ->and($client->password_invite_version)->toBe(1)
        ->and(Hash::check('pat@example.com', $client->password))->toBeFalse();

    $this->assertDatabaseHas('customer_user', [
        'customer_id' => $customer->id,
        'user_id' => $client->id,
    ]);

    Mail::assertSent(PortalInviteMail::class, function (PortalInviteMail $mail) use ($client, $customer) {
        return $mail->hasTo($client->email)
            && $mail->user->is($client)
            && $mail->customer->is($customer)
            && $mail->expiresInHours === 72
            && str_contains($mail->setPasswordUrl, 'signature=')
            && str_contains($mail->setPasswordUrl, 'expires=')
            && str_contains($mail->setPasswordUrl, 'v='.$client->password_invite_version);
    });
    Mail::assertNotSent(PortalAccessGrantedMail::class);
});

it('blocks login until the invited client sets a password from the signed link', function () {
    Mail::fake();

    $admin = User::factory()->admin()->create();
    $customer = Customer::factory()->create();

    $this->actingAs($admin)
        ->post(route('customers.portal-users.store', $customer), [
            'email' => 'newclient@example.com',
            'name' => 'New Client',
        ]);

    $client = User::query()->where('email', 'newclient@example.com')->first();
    $inviteUrl = null;

    Mail::assertSent(PortalInviteMail::class, function (PortalInviteMail $mail) use (&$inviteUrl) {
        $inviteUrl = $mail->setPasswordUrl;

        return $mail->hasTo('newclient@example.com');
    });

    $this->post(route('logout'));

    $this->post(route('login'), [
        'email' => 'newclient@example.com',
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();

    $this->get($inviteUrl)
        ->assertOk()
        ->assertSee('Set your password')
        ->assertSee('newclient@example.com');

    $this->post($inviteUrl, [
        'password' => 'portal-pass-1',
        'password_confirmation' => 'portal-pass-1',
    ])->assertRedirect(route('login'))
        ->assertSessionHas('status', 'Password set. You can sign in now.');

    $client->refresh();

    expect($client->mustSetPassword())->toBeFalse()
        ->and(Hash::check('portal-pass-1', $client->password))->toBeTrue();

    $this->post(route('login'), [
        'email' => 'newclient@example.com',
        'password' => 'portal-pass-1',
    ])->assertRedirect(route('portal.home'));

    $this->assertAuthenticatedAs($client);

    $this->get(route('portal.home'))
        ->assertOk()
        ->assertSee('Client portal');
});

it('rejects an expired or unsigned set-password link without changing the password', function () {
    Mail::fake();
    $this->freezeTime();

    $admin = User::factory()->admin()->create();
    $customer = Customer::factory()->create();

    $this->actingAs($admin)
        ->post(route('customers.portal-users.store', $customer), [
            'email' => 'late@example.com',
        ])
        ->assertRedirect(route('customers.show', $customer));

    $client = User::query()->where('email', 'late@example.com')->first();

    expect($client)->not->toBeNull();

    $inviteUrl = null;

    Mail::assertSent(PortalInviteMail::class, function (PortalInviteMail $mail) use (&$inviteUrl) {
        $inviteUrl = $mail->setPasswordUrl;

        return true;
    });

    $this->get(route('portal.password.create', $client))
        ->assertForbidden()
        ->assertSee('invalid or has expired');

    $this->travel(73)->hours();

    $this->get($inviteUrl)
        ->assertForbidden()
        ->assertSee('invalid or has expired');

    $this->post($inviteUrl, [
        'password' => 'too-late-99',
        'password_confirmation' => 'too-late-99',
    ])->assertForbidden();

    expect($client->fresh()->mustSetPassword())->toBeTrue()
        ->and(Hash::check('too-late-99', $client->fresh()->password))->toBeFalse();
});

it('rejects a second use of the same set-password link after the first succeeds', function () {
    Mail::fake();

    $admin = User::factory()->admin()->create();
    $customer = Customer::factory()->create();

    $this->actingAs($admin)
        ->post(route('customers.portal-users.store', $customer), [
            'email' => 'once@example.com',
            'name' => 'Once Client',
        ]);

    $inviteUrl = null;

    Mail::assertSent(PortalInviteMail::class, function (PortalInviteMail $mail) use (&$inviteUrl) {
        $inviteUrl = $mail->setPasswordUrl;

        return $mail->hasTo('once@example.com');
    });

    $this->post($inviteUrl, [
        'password' => 'first-pass-1',
        'password_confirmation' => 'first-pass-1',
    ])->assertRedirect(route('login'));

    $client = User::query()->where('email', 'once@example.com')->first();

    $this->get($inviteUrl)
        ->assertForbidden()
        ->assertSee('already been used');

    $this->post($inviteUrl, [
        'password' => 'second-pass-9',
        'password_confirmation' => 'second-pass-9',
    ])->assertForbidden()
        ->assertSee('already been used');

    expect($client->fresh()->mustSetPassword())->toBeFalse()
        ->and(Hash::check('first-pass-1', $client->fresh()->password))->toBeTrue()
        ->and(Hash::check('second-pass-9', $client->fresh()->password))->toBeFalse();
});

it('invalidates an older signed link when portal access is granted again', function () {
    Mail::fake();

    $admin = User::factory()->admin()->create();
    $firstCustomer = Customer::factory()->create(['name' => 'First Site']);
    $secondCustomer = Customer::factory()->create(['name' => 'Second Site']);

    $this->actingAs($admin)
        ->post(route('customers.portal-users.store', $firstCustomer), [
            'email' => 'pending@example.com',
            'name' => 'Pending Client',
        ]);

    $oldUrl = null;

    Mail::assertSent(PortalInviteMail::class, function (PortalInviteMail $mail) use (&$oldUrl) {
        $oldUrl = $mail->setPasswordUrl;

        return $mail->hasTo('pending@example.com');
    });

    Mail::fake();

    $this->actingAs($admin)
        ->post(route('customers.portal-users.store', $secondCustomer), [
            'email' => 'pending@example.com',
        ])
        ->assertRedirect(route('customers.show', $secondCustomer))
        ->assertSessionHas('status', 'Portal invite sent. The set-password link expires in 72 hours.');

    $newUrl = null;

    Mail::assertSent(PortalInviteMail::class, function (PortalInviteMail $mail) use (&$newUrl) {
        $newUrl = $mail->setPasswordUrl;

        return $mail->hasTo('pending@example.com');
    });

    $client = User::query()->where('email', 'pending@example.com')->first();

    expect($oldUrl)->not->toBe($newUrl)
        ->and($client->password_invite_version)->toBe(2)
        ->and($client->mustSetPassword())->toBeTrue();

    $this->get($oldUrl)
        ->assertForbidden()
        ->assertSee('invalid or has expired');

    $this->post($oldUrl, [
        'password' => 'stale-pass-1',
        'password_confirmation' => 'stale-pass-1',
    ])->assertForbidden();

    expect($client->fresh()->mustSetPassword())->toBeTrue()
        ->and(Hash::check('stale-pass-1', $client->fresh()->password))->toBeFalse();

    $this->get($newUrl)
        ->assertOk()
        ->assertSee('Set your password');

    $this->post($newUrl, [
        'password' => 'fresh-pass-1',
        'password_confirmation' => 'fresh-pass-1',
    ])->assertRedirect(route('login'));

    expect($client->fresh()->mustSetPassword())->toBeFalse()
        ->and(Hash::check('fresh-pass-1', $client->fresh()->password))->toBeTrue();
});

it('attaches an existing client without forcing a password reset and emails access', function () {
    Mail::fake();

    $admin = User::factory()->admin()->create();
    $firstCustomer = Customer::factory()->create(['name' => 'First Site']);
    $secondCustomer = Customer::factory()->create(['name' => 'Second Site']);
    $client = User::factory()->client()->create([
        'email' => 'existing@example.com',
        'password' => 'already-set-1',
    ]);
    $firstCustomer->portalUsers()->attach($client);

    $this->actingAs($admin)
        ->post(route('customers.portal-users.store', $secondCustomer), [
            'email' => 'existing@example.com',
        ])
        ->assertRedirect(route('customers.show', $secondCustomer))
        ->assertSessionHas('status', 'Portal access granted. We emailed them about this customer.');

    expect($client->fresh()->mustSetPassword())->toBeFalse()
        ->and(Hash::check('already-set-1', $client->fresh()->password))->toBeTrue();

    $this->assertDatabaseHas('customer_user', [
        'customer_id' => $secondCustomer->id,
        'user_id' => $client->id,
    ]);

    Mail::assertSent(PortalAccessGrantedMail::class, function (PortalAccessGrantedMail $mail) use ($client, $secondCustomer) {
        return $mail->hasTo($client->email) && $mail->customer->is($secondCustomer);
    });
    Mail::assertNotSent(PortalInviteMail::class);
});

it('removes portal access for a customer without deleting the user', function () {
    $admin = User::factory()->admin()->create();
    $customer = Customer::factory()->create();
    $client = User::factory()->client()->create();
    $customer->portalUsers()->attach($client);

    $this->actingAs($admin)
        ->delete(route('customers.portal-users.destroy', [$customer, $client]))
        ->assertRedirect(route('customers.show', $customer))
        ->assertSessionHas('status', 'Portal access removed.');

    $this->assertDatabaseMissing('customer_user', [
        'customer_id' => $customer->id,
        'user_id' => $client->id,
    ]);
    $this->assertModelExists($client);
});

it('escapes customer names in portal invite mail', function () {
    $user = User::factory()->client()->pendingPassword()->make([
        'name' => 'Pat <em>Client</em>',
        'email' => 'pat@example.com',
    ]);
    $customer = Customer::factory()->make([
        'name' => "O'Reilly <script>alert('xss')</script>",
    ]);

    $mail = new PortalInviteMail($user, $customer, 'https://example.test/set-password', 72);
    $html = $mail->render();

    expect($html)
        ->toContain("O'Reilly")
        ->toContain('&lt;script&gt;')
        ->not->toContain("<script>alert('xss')</script>");

    $mail->assertSeeInText("O'Reilly")
        ->assertSeeInText('Set your password')
        ->assertSeeInText('72 hours');
});
