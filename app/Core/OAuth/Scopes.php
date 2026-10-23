<?php

namespace App\Core\OAuth;

use App\Enums\ConnectorScope;
use App\Models\User;

class Scopes
{
    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_map(fn (ConnectorScope $scope): string => $scope->value, ConnectorScope::cases());
    }

    /**
     * Whoever may edit the website (site.edit) may grant every scope; nobody else gets any.
     *
     * @return list<string>
     */
    public static function allowedFor(User $user): array
    {
        return $user->can('site.edit') ? self::all() : [];
    }

    public static function describe(string $scope): string
    {
        return match (ConnectorScope::tryFrom($scope)) {
            ConnectorScope::Director => 'Edit the website: create, edit and delete pages, change the facts and the theme, restore earlier versions. Changes are live right away.',
            ConnectorScope::Operator => 'Read the website: pages, facts, theme, lint checks and page history.',
            null => 'Unknown scope.',
        };
    }

    /**
     * Scopes the consent screen offers unticked: granted only when the user ticks them.
     *
     * @return list<string>
     */
    public static function optIn(): array
    {
        return [];
    }

    /**
     * Splits a space-delimited scope parameter (RFC 6749 §3.3); unknown names are kept so callers can reject them.
     *
     * @return list<string>
     */
    public static function parse(?string $scope): array
    {
        if ($scope === null) {
            return [];
        }

        return array_values(array_unique(preg_split('/ +/', trim($scope), -1, PREG_SPLIT_NO_EMPTY) ?: []));
    }

    /**
     * Known scopes only, without duplicates, in the canonical order of all().
     *
     * @param  array<array-key, mixed>  $scopes
     * @return list<string>
     */
    public static function normalize(array $scopes): array
    {
        return array_values(array_intersect(self::all(), array_filter($scopes, is_string(...))));
    }
}
