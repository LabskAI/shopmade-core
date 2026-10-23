<?php

use App\Core\Agents\Actor;
use App\Core\Agents\AgentRefusedException;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

it('identifies a dashboard or chat user', function () {
    $user = User::factory()->admin()->create(['name' => 'Ana Ruiz']);

    $actor = Actor::user($user);

    expect($actor->key())->toBe('user:'.$user->id)
        ->and($actor->via())->toBe('dashboard')
        ->and($actor->label())->toBe('Ana Ruiz')
        ->and($actor->user()?->is($user))->toBeTrue()
        ->and($actor->isConnector())->toBeFalse()
        ->and($actor->clientId())->toBeNull()
        ->and(Actor::user($user, 'chat')->via())->toBe('chat');
});

it('identifies a connector by user and client', function () {
    $user = User::factory()->superAdmin()->create(['name' => 'Ana Ruiz']);

    $actor = Actor::connector($user, 'client-123');

    expect($actor->key())->toBe("connector:{$user->id}:client-123")
        ->and($actor->via())->toBe('mcp')
        ->and($actor->label())->toBe('Claude (director) for Ana Ruiz')
        ->and($actor->user()?->is($user))->toBeTrue()
        ->and($actor->isConnector())->toBeTrue()
        ->and($actor->clientId())->toBe('client-123');
});

it('identifies the command line and the scheduler without a user', function () {
    $cli = Actor::cli();
    $system = Actor::system();

    expect($cli->key())->toBe('cli')
        ->and($cli->via())->toBe('cli')
        ->and($cli->user())->toBeNull()
        ->and($system->key())->toBe('system:schedule')
        ->and($system->via())->toBe('schedule')
        ->and($system->user())->toBeNull()
        ->and(Actor::system('cleanup')->key())->toBe('system:cleanup');
});

it('restores an actor that went through a queue payload', function (Closure $make) {
    $user = User::factory()->superAdmin()->create(['name' => 'Ana Ruiz']);
    $actor = $make($user);

    $restored = Actor::fromArray(json_decode(json_encode($actor->toArray()), true));

    expect($restored)
        ->key()->toBe($actor->key())
        ->via()->toBe($actor->via())
        ->label()->toBe($actor->label())
        ->clientId()->toBe($actor->clientId())
        ->isConnector()->toBe($actor->isConnector())
        ->and($restored->userModel()?->getKey())->toBe($actor->userModel()?->getKey());
})->with([
    'dashboard user' => [fn (User $user): Actor => Actor::forUser($user)],
    'chat user' => [fn (User $user): Actor => Actor::forUser($user, 'chat')],
    'connector' => [fn (User $user): Actor => Actor::connector($user, 'client-123')],
    'command line' => [fn (): Actor => Actor::cli()],
    'scheduler' => [fn (): Actor => Actor::system('cleanup')],
]);

it('refuses to restore an actor whose user was deleted', function () {
    $user = User::factory()->admin()->create();
    $payload = Actor::connector($user, 'client-123')->toArray();
    $user->delete();

    Actor::fromArray($payload);
})->throws(ModelNotFoundException::class);

it('refuses to restore a person as an actor without a user', function () {
    Actor::fromArray(['key' => 'user:5', 'via' => 'dashboard', 'label' => 'Ana Ruiz', 'user_id' => null, 'client_id' => null]);
})->throws(InvalidArgumentException::class, 'The actor [user:5] cannot be restored without its user.');

it('maps refusal reasons to MCP error codes', function (string $reason, int $code) {
    $refusal = new AgentRefusedException('Refused.', $reason, 'Try again later.');

    expect($refusal->mcpCode())->toBe($code)
        ->and($refusal->hint)->toBe('Try again later.');
})->with([
    'not found' => ['not_found', -32004],
    'needs rebase' => ['needs_rebase', -32004],
    'path refused' => ['path_refused', -32005],
    'budget exhausted' => ['budget_exhausted', -32006],
    'forbidden' => ['forbidden', -32002],
    'risk gate' => ['risk_gate', -32002],
    'invalid params' => ['invalid_params', -32602],
    'unknown reason' => ['something_else', -32004],
]);
