<?php

namespace App\Enums;

enum PluginSource: string
{
    case Local = 'local';
    case Vendor = 'vendor';
}
