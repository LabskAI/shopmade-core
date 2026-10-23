<?php

namespace App\Enums;

enum PluginStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';
    case Removed = 'removed';
}
