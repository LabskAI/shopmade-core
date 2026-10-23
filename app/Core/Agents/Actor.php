<?php

namespace App\Core\Agents;

use App\Models\User;
use BadMethodCallException;
use InvalidArgumentException;

/**
 * Who is acting on the agents surface. `user` is both a static constructor and an instance accessor
 * (PHP cannot declare both), so it is dispatched through __callStatic / __call; forUser() and
 * userModel() are the typed equivalents. toArray() / fromArray() carry an actor into a queued job.
 *
 * @method static self user(User $user, string $via = 'dashboard')
 * @method ?User user()
 */
final readonly class Actor
{
    private function __construct(
        private string $key,
        private string $via,
        private string $label,
        private ?User $userModel = null,
        private ?string $clientId = null,
    ) {}

    public static function forUser(User $user, string $via = 'dashboard'): self
    {
        return new self('user:'.$user->id, $via, (string) $user->name, $user);
    }

    public static function connector(User $user, string $clientId): self
    {
        return new self("connector:{$user->id}:{$clientId}", 'mcp', "Claude (director) for {$user->name}", $user, $clientId);
    }

    public static function cli(): self
    {
        return new self('cli', 'cli', 'Command line');
    }

    public static function system(string $what = 'schedule'): self
    {
        return new self('system:'.$what, 'schedule', "System ({$what})");
    }

    /**
     * Rebuilds what toArray() described through the named constructors, re-fetching the user by id: a user or
     * connector actor whose user was deleted throws ModelNotFoundException, and an actor without a user can
     * only be the command line or the system, never a trusted stand-in for a person.
     *
     * @param  array{key?: mixed, via?: mixed, label?: mixed, user_id?: mixed, client_id?: mixed}  $data
     */
    public static function fromArray(array $data): self
    {
        $key = is_string($data['key'] ?? null) ? $data['key'] : '';
        $userId = $data['user_id'] ?? null;
        $clientId = is_string($data['client_id'] ?? null) && $data['client_id'] !== '' ? $data['client_id'] : null;

        if ($userId === null) {
            return match (true) {
                $key === 'cli' => self::cli(),
                str_starts_with($key, 'system:') && strlen($key) > strlen('system:') => self::system(substr($key, strlen('system:'))),
                default => throw new InvalidArgumentException("The actor [{$key}] cannot be restored without its user."),
            };
        }

        if (! is_numeric($userId)) {
            throw new InvalidArgumentException('The actor user_id must be a user id.');
        }

        $user = User::query()->findOrFail((int) $userId);

        if ($clientId !== null) {
            return self::connector($user, $clientId);
        }

        return self::forUser($user, is_string($data['via'] ?? null) && $data['via'] !== '' ? $data['via'] : 'dashboard');
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    public static function __callStatic(string $method, array $arguments): self
    {
        if ($method === 'user') {
            return self::forUser(...$arguments);
        }

        throw new BadMethodCallException('Call to undefined method '.self::class.'::'.$method.'()');
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): ?User
    {
        if ($method === 'user') {
            return $this->userModel;
        }

        throw new BadMethodCallException('Call to undefined method '.self::class.'::'.$method.'()');
    }

    public function key(): string
    {
        return $this->key;
    }

    public function via(): string
    {
        return $this->via;
    }

    public function userModel(): ?User
    {
        return $this->userModel;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function isConnector(): bool
    {
        return $this->clientId !== null;
    }

    public function clientId(): ?string
    {
        return $this->clientId;
    }

    /**
     * Plain data for a queued job; Actor::fromArray() turns it back into this actor.
     *
     * @return array{key: string, via: string, label: string, user_id: ?int, client_id: ?string}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'via' => $this->via,
            'label' => $this->label,
            'user_id' => $this->userModel === null ? null : (int) $this->userModel->getKey(),
            'client_id' => $this->clientId,
        ];
    }
}
