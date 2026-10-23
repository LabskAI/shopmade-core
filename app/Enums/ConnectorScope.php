<?php

namespace App\Enums;

enum ConnectorScope: string
{
    case Director = 'director';
    case Operator = 'operator';

    public function label(): string
    {
        return match ($this) {
            self::Director => 'Director',
            self::Operator => 'Operator',
        };
    }
}
