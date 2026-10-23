<?php

namespace App\Http\Controllers\Front;

use App\Core\Front\FrontDiscovery;
use App\Core\Front\FrontSite;
use Symfony\Component\HttpFoundation\Response;

class DiscoveryController extends FrontController
{
    public function robots(FrontSite $site, FrontDiscovery $discovery): Response
    {
        return $this->file($site, fn (): string => $discovery->robots($site), 'text/plain');
    }

    public function sitemap(FrontSite $site, FrontDiscovery $discovery): Response
    {
        return $this->file($site, fn (): string => $discovery->sitemap($site), 'application/xml');
    }

    public function llms(FrontSite $site, FrontDiscovery $discovery): Response
    {
        return $this->file($site, fn (): string => $discovery->llms($site), 'text/plain');
    }

    public function llmsFull(FrontSite $site, FrontDiscovery $discovery): Response
    {
        return $this->file($site, fn (): string => $discovery->llmsFull($site), 'text/plain');
    }

    /**
     * @param  callable(): string  $contents
     */
    private function file(FrontSite $site, callable $contents, string $type): Response
    {
        abort_unless($site->exists(), 404);

        return response($contents(), 200, ['Content-Type' => $type.'; charset=UTF-8']);
    }
}
