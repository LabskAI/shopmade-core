<?php

use App\Core\Agents\Actor;
use App\Core\Agents\AgentRefusedException;
use App\Core\OAuth\ConnectorGrants;
use App\Models\ConnectorGrant;
use App\Models\OAuthClient;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

it('lists active connectors next to the connect instructions', function () {
    config(['agents.company' => 'acme']);
    $owner = User::factory()->superAdmin()->create(['name' => 'Ana Owner']);
    $grant = ConnectorGrant::factory()
        ->for($owner)
        ->for(OAuthClient::factory()->state(['name' => 'Claude for Ana']), 'client')
        ->create(['scopes' => ['director', 'operator']]);
    $this->mock(ConnectorGrants::class, function ($mock) use ($grant): void {
        $mock->shouldReceive('active')->once()->andReturn(new Collection([$grant]));
    });

    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('admin.connectors.index'))
        ->assertOk()
        ->assertSee('A connector lets a Claude session outside this server read and edit your website: pages, facts and theme. Edits are live at once and kept in each page\'s history. Every tool call is logged with the connector\'s name.', false)
        ->assertSeeInOrder(['Claude for Ana', 'Ana Owner', 'director, operator', 'Never'])
        ->assertSee(route('admin.connectors.update', $grant))
        ->assertSee(route('admin.connectors.destroy', $grant))
        ->assertSee('value="'.url('mcp/agents').'"', false)
        ->assertSee('Copy URL')
        ->assertSee('Name: <code>Shopmade acme</code>', false);
});

it('shows an empty state when no connector is active', function () {
    $this->mock(ConnectorGrants::class, function ($mock): void {
        $mock->shouldReceive('active')->once()->andReturn(new Collection);
    });

    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('admin.connectors.index'))
        ->assertOk()
        ->assertSee('No connectors yet');
});

it('offers no scopes when the connector user may not edit the site', function () {
    $grant = ConnectorGrant::factory()->for(User::factory()->user())->create(['scopes' => ['operator']]);
    $this->mock(ConnectorGrants::class, function ($mock) use ($grant): void {
        $mock->shouldReceive('active')->once()->andReturn(new Collection([$grant]));
    });

    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('admin.connectors.index'))
        ->assertOk()
        ->assertDontSee('value="operator"', false)
        ->assertSee('This user may not hold any connector scope.');
});

it('explains that adding a scope needs the user to reconnect', function () {
    $grant = ConnectorGrant::factory()->create(['scopes' => ['operator']]);
    $this->mock(ConnectorGrants::class, function ($mock) use ($grant): void {
        $mock->shouldReceive('active')->once()->andReturn(new Collection([$grant]));
    });

    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('admin.connectors.index'))
        ->assertOk()
        ->assertSee('value="operator"', false)
        ->assertDontSee('value="director"', false)
        ->assertSee('director: not approved for this connector')
        ->assertSee('To add it, the user reconnects the connector and approves it on the consent screen.');
});

it('changes the scopes of a connector', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $grant = ConnectorGrant::factory()->create(['scopes' => ['director', 'operator']]);
    $this->mock(ConnectorGrants::class, function ($mock) use ($grant, $superAdmin): void {
        $mock->shouldReceive('changeScopes')
            ->once()
            ->withArgs(fn (ConnectorGrant $changed, array $scopes, Actor $actor): bool => $changed->is($grant)
                && $scopes === ['director']
                && $actor->key() === 'user:'.$superAdmin->id)
            ->andReturn($grant);
    });

    $this->actingAs($superAdmin)
        ->put(route('admin.connectors.update', $grant), ['scopes' => ['director']])
        ->assertRedirect(route('admin.connectors.index'))
        ->assertSessionHas('status', 'Scopes updated. Tokens already issued follow the new scopes on their next call.');
});

it('rejects a scope the connector user may not hold', function () {
    $grant = ConnectorGrant::factory()->for(User::factory()->user())->create(['scopes' => ['operator']]);
    $this->mock(ConnectorGrants::class, function ($mock): void {
        $mock->shouldReceive('changeScopes')->never();
    });

    $this->actingAs(User::factory()->superAdmin()->create())
        ->from(route('admin.connectors.index'))
        ->put(route('admin.connectors.update', $grant), ['scopes' => ['operator']])
        ->assertRedirect(route('admin.connectors.index'))
        ->assertSessionHasErrors(['scopes.0' => 'operator is not a scope this user may grant.']);
});

it('requires at least one scope', function () {
    $grant = ConnectorGrant::factory()->create(['scopes' => ['director']]);
    $this->mock(ConnectorGrants::class, function ($mock): void {
        $mock->shouldReceive('changeScopes')->never();
    });

    $this->actingAs(User::factory()->superAdmin()->create())
        ->from(route('admin.connectors.index'))
        ->put(route('admin.connectors.update', $grant), ['scopes' => []])
        ->assertSessionHasErrors(['scopes' => 'Keep at least one scope. To remove all access, revoke the connector.']);
});

it('shows the reason when a scope change is refused', function () {
    $grant = ConnectorGrant::factory()->create(['scopes' => ['operator']]);
    $this->mock(ConnectorGrants::class, function ($mock): void {
        $mock->shouldReceive('changeScopes')
            ->once()
            ->andThrow(new AgentRefusedException('Scopes can only be removed here; director was not approved for this connector.', 'invalid_params'));
    });

    $this->actingAs(User::factory()->superAdmin()->create())
        ->put(route('admin.connectors.update', $grant), ['scopes' => ['operator', 'director']])
        ->assertRedirect(route('admin.connectors.index'))
        ->assertSessionHasErrors(['scopes' => 'Scopes can only be removed here; director was not approved for this connector.']);
});

it('revokes a connector', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $grant = ConnectorGrant::factory()->create();
    $this->mock(ConnectorGrants::class, function ($mock) use ($grant, $superAdmin): void {
        $mock->shouldReceive('revoke')
            ->once()
            ->withArgs(fn (ConnectorGrant $revoked, Actor $actor): bool => $revoked->is($grant) && $actor->key() === 'user:'.$superAdmin->id);
    });

    $this->actingAs($superAdmin)
        ->delete(route('admin.connectors.destroy', $grant))
        ->assertRedirect(route('admin.connectors.index'))
        ->assertSessionHas('status', 'Connector revoked. Its tokens stop working immediately.');
});

it('does not revoke a connector twice', function () {
    $grant = ConnectorGrant::factory()->create(['revoked_at' => now()]);
    $this->mock(ConnectorGrants::class, function ($mock): void {
        $mock->shouldReceive('revoke')->never();
    });

    $this->actingAs(User::factory()->superAdmin()->create())
        ->delete(route('admin.connectors.destroy', $grant))
        ->assertRedirect(route('admin.connectors.index'))
        ->assertSessionHas('status', 'This connector was already revoked.');
});

it('forbids staff users from connectors', function () {
    $grant = ConnectorGrant::factory()->create();
    $this->mock(ConnectorGrants::class, function ($mock): void {
        $mock->shouldReceive('active')->never();
        $mock->shouldReceive('changeScopes')->never();
        $mock->shouldReceive('revoke')->never();
    });
    $admin = User::factory()->user()->create();

    $this->actingAs($admin)->get(route('admin.connectors.index'))->assertForbidden();
    $this->actingAs($admin)->put(route('admin.connectors.update', $grant), ['scopes' => ['director']])->assertForbidden();
    $this->actingAs($admin)->delete(route('admin.connectors.destroy', $grant))->assertForbidden();
});
