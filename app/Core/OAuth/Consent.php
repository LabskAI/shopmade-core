<?php

namespace App\Core\OAuth;

use App\Models\User;

/**
 * What the consent screen offers for a request: scopes granted on approval, opt-in scopes granted only
 * when ticked (none today), and requested scopes beyond the user's role.
 */
final readonly class Consent
{
    /**
     * @param  list<string>  $granted
     * @param  list<string>  $optional
     * @param  list<string>  $unavailable
     */
    public function __construct(
        public array $granted,
        public array $optional,
        public array $unavailable,
    ) {}

    public static function for(AuthorizationRequest $request, User $user): self
    {
        $eligible = Scopes::allowedFor($user);
        $grantable = array_values(array_intersect($request->scopes, $eligible));
        $optional = array_values(array_intersect($grantable, Scopes::optIn()));

        return new self(
            granted: array_values(array_diff($grantable, $optional)),
            optional: $optional,
            unavailable: array_values(array_diff($request->scopes, $eligible)),
        );
    }

    public function isPossible(): bool
    {
        return $this->granted !== [] || $this->optional !== [];
    }

    /**
     * @param  array<array-key, mixed>  $ticked  the opt-in scopes the user ticked
     * @return list<string>
     */
    public function approve(array $ticked): array
    {
        return Scopes::normalize([...$this->granted, ...array_intersect($this->optional, array_filter($ticked, is_string(...)))]);
    }
}
