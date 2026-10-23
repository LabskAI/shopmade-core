<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case User = 'user';
    case Client = 'client';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super admin',
            self::Admin => 'Admin',
            self::User => 'User',
            self::Client => 'Client',
        };
    }
}
