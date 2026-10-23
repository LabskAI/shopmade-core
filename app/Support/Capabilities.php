<?php

namespace App\Support;

use App\Models\User;

class Capabilities
{
    public const UsersManage = 'users.manage';

    public const PortalAccess = 'portal.access';

    public const StaffAccess = 'staff.access';

    public const SiteEdit = 'site.edit';

    public const ConnectorsManage = 'connectors.manage';

    /**
     * @return array<string, list<string>>
     */
    public static function map(): array
    {
        /** @var array<string, list<string>> $capabilities */
        $capabilities = config('capabilities', []);

        return $capabilities;
    }

    public static function allows(?User $user, string $capability): bool
    {
        if ($user === null) {
            return false;
        }

        $roles = self::map()[$capability] ?? [];

        if (in_array('*', $roles, true)) {
            return true;
        }

        return in_array($user->role->value, $roles, true);
    }
}
