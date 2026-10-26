<?php

namespace App\Http\Controllers\Front;

use App\Core\Front\FrontAssets;
use App\Core\Front\FrontSite;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AssetController extends FrontController
{
    /**
     * A company file under assets/ (FrontAssets: fixed types, no symlinks, nothing outside the folder). URLs carry a
     * content hash, so responses cache for a year. SVGs are served sandboxed so a script inside one never runs.
     */
    public function show(FrontSite $site, FrontAssets $assets, string $path): BinaryFileResponse
    {
        abort_unless($site->exists(), 404);

        $resolved = $assets->resolve($site->company(), $path, $site->basePath());

        abort_if($resolved === null, 404);

        $type = (string) $assets->mime($path);
        $response = response()->file($resolved, [
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; font-src 'self'; sandbox",
            'Cross-Origin-Resource-Policy' => 'same-site',
        ]);
        $response->headers->set('Content-Type', $type);

        return $response;
    }

    /**
     * Well-known icons and the web manifest at the front root, from assets/icons/.
     */
    public function wellKnown(FrontSite $site, FrontAssets $assets, string $name): BinaryFileResponse
    {
        abort_unless(isset(FrontSite::WELL_KNOWN_ICONS[$name]), 404);

        return $this->show($site, $assets, FrontSite::WELL_KNOWN_ICONS[$name]);
    }
}
