<?php

return [

    /*
    | Proxies whose X-Forwarded-* headers are trusted, so HTTPS terminated in front of the app (Easypanel,
    | Traefik, Coolify) still gives https:// URLs, OAuth metadata and secure cookies. A comma-separated list
    | of IPs or CIDRs, or `*` to trust the calling proxy. The production image sets `*`.
    */

    'proxies' => env('TRUSTED_PROXIES'),

];
