<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * A throwaway repo root holding a copy of the real fronts/demo; agents.paths.repo points at it so saves never touch
 * the real front. Returns the copied front folder.
 */
function siteSandbox(): string
{
    $root = realpath(sys_get_temp_dir()).'/shopmade-site-'.Str::lower(Str::random(10));

    File::ensureDirectoryExists($root.'/fronts');
    File::copyDirectory(base_path('fronts/demo'), $root.'/fronts/demo');

    config(['agents.company' => 'demo', 'agents.paths.repo' => $root]);
    test()->beforeApplicationDestroyed(fn () => File::deleteDirectory($root));

    return $root.'/fronts/demo';
}
