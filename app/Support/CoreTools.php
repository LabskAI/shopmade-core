<?php

namespace App\Support;

use App\Core\Packages\Hooks;
use App\Enums\UserRole;
use App\Models\User;

class CoreTools
{
    /**
     * @return list<array{slug: string, label: string, group: string, route: string, pattern: string, view_roles: list<string>, manage_roles: list<string>}>
     */
    public static function all(): array
    {
        /** @var list<array{slug: string, label: string, group: string, route: string, pattern: string, view_roles: list<string>, manage_roles: list<string>}> $tools */
        $tools = config('core-tools', []);

        return [...$tools, ...app(Hooks::class)->tools()];
    }

    /**
     * @return array<string, list<array{slug: string, label: string, group: string, route: string, pattern: string, view_roles: list<string>, manage_roles: list<string>}>>
     */
    public static function navGroups(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $groups = [];

        foreach (self::all() as $tool) {
            if (! self::allows($user, $tool['slug'], 'view')) {
                continue;
            }

            if (! app('router')->has($tool['route'])) {
                continue;
            }

            $groups[$tool['group']][] = $tool;
        }

        return $groups;
    }

    public static function allows(User $user, string $slug, string $ability = 'view'): bool
    {
        $tool = collect(self::all())->firstWhere('slug', $slug);

        if (! is_array($tool)) {
            return false;
        }

        $roles = $ability === 'manage' ? $tool['manage_roles'] : $tool['view_roles'];

        if (in_array('*', $roles, true)) {
            return true;
        }

        if (in_array($user->role->value, $roles, true)) {
            return true;
        }

        return $user->role === UserRole::SuperAdmin
            && in_array(UserRole::Admin->value, $roles, true);
    }
}
