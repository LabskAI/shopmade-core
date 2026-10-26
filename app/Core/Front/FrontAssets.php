<?php

namespace App\Core\Front;

/**
 * Files under content/<company>/assets that the kit serves: a fixed set of static types, regular files only, never a
 * symlink (file or folder) or a path that leaves the assets folder. URLs carry a content hash, so they cache forever.
 */
class FrontAssets
{
    /**
     * @var array<string, string>
     */
    public const TYPES = [
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
        'gif' => 'image/gif',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'woff2' => 'font/woff2',
        'ico' => 'image/x-icon',
        'js' => 'text/javascript',
        'webmanifest' => 'application/manifest+json',
    ];

    public const IMAGE_TYPES = ['svg', 'png', 'jpg', 'jpeg', 'webp', 'avif', 'gif'];

    public function __construct(private FrontKit $kit) {}

    /**
     * Path relative to assets/ ("logos/mark.svg") for "assets/logos/mark.svg" or "asset:logos/mark.svg", or null when
     * the reference is not a serveable asset path.
     */
    public function normalize(string $reference): ?string
    {
        $path = trim($reference);

        if (str_starts_with($path, 'asset:')) {
            $path = substr($path, strlen('asset:'));
        } elseif (str_starts_with($path, 'assets/')) {
            $path = substr($path, strlen('assets/'));
        } elseif (str_starts_with($path, '/assets/')) {
            $path = substr($path, strlen('/assets/'));
        }

        if ($path === '' || str_contains($path, "\0") || str_contains($path, '\\') || str_contains($path, '..')) {
            return null;
        }

        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*(?:\.[A-Za-z0-9_-]+)*(?:\/[A-Za-z0-9][A-Za-z0-9_-]*(?:\.[A-Za-z0-9_-]+)*)*$/', $path) !== 1) {
            return null;
        }

        return $this->mime($path) !== null ? $path : null;
    }

    public function mime(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return self::TYPES[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? null;
    }

    public function isImage(string $path): bool
    {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::IMAGE_TYPES, true);
    }

    /**
     * Absolute path of a serveable asset, or null.
     */
    public function resolve(string $company, string $reference, ?string $basePath = null): ?string
    {
        $relative = $this->normalize($reference);

        if ($relative === null || ! $this->kit->exists($company, $basePath)) {
            return null;
        }

        $assets = $this->kit->directory($company, $basePath).'/assets';
        $path = $assets;

        if (is_link($assets) || ! is_dir($assets)) {
            return null;
        }

        foreach (explode('/', $relative) as $segment) {
            $path .= '/'.$segment;

            if (is_link($path)) {
                return null;
            }
        }

        $root = realpath($assets);
        $resolved = is_file($path) ? realpath($path) : false;

        if ($root === false || $resolved === false || ! str_starts_with($resolved, $root.'/')) {
            return null;
        }

        return $resolved;
    }

    public function version(string $absolutePath): string
    {
        $hash = hash_file('xxh3', $absolutePath);

        return is_string($hash) && $hash !== '' ? substr($hash, 0, 12) : (string) filemtime($absolutePath);
    }

    /**
     * Intrinsic size of an image: getimagesize for bitmaps; for SVG the width/height attributes, else the viewBox.
     *
     * @return array{0: int, 1: int}|null
     */
    public function size(string $absolutePath): ?array
    {
        if (strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION)) !== 'svg') {
            $size = @getimagesize($absolutePath);

            return is_array($size) && $size[0] > 0 && $size[1] > 0 ? [(int) $size[0], (int) $size[1]] : null;
        }

        $head = (string) file_get_contents($absolutePath, false, null, 0, 4096);

        if (preg_match('/<svg\b[^>]*>/is', $head, $tag) !== 1) {
            return null;
        }

        $width = preg_match('/\swidth\s*=\s*["\']\s*(\d+(?:\.\d+)?)(?:px)?\s*["\']/i', $tag[0], $w) === 1 ? (float) $w[1] : null;
        $height = preg_match('/\sheight\s*=\s*["\']\s*(\d+(?:\.\d+)?)(?:px)?\s*["\']/i', $tag[0], $h) === 1 ? (float) $h[1] : null;

        if (($width === null || $height === null)
            && preg_match('/\sviewBox\s*=\s*["\']\s*-?[\d.]+[\s,]+-?[\d.]+[\s,]+([\d.]+)[\s,]+([\d.]+)\s*["\']/i', $tag[0], $box) === 1) {
            $width = (float) $box[1];
            $height = (float) $box[2];
        }

        return $width !== null && $height !== null && $width >= 1 && $height >= 1 ? [(int) round($width), (int) round($height)] : null;
    }
}
