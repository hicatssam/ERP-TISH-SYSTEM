<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

final class PublicImageUrl
{
    private const DISK_PREFIXES = [
        'images/sweets-menu/',
        'branding/',
        'products/',
        'product-variants/',
        'menu-cutouts/',
        'categories/',
        'customer-menu/',
        'customer-display/',
        'menu-banners/',
        'banners/',
        'restaurant-menu/',
        'payment-methods/',
    ];

    public static function url(?string $path): ?string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return null;
        }

        if (preg_match('#^(https?://|//|data:image/)#i', $path)) {
            return $path;
        }

        $normalized = ltrim(str_replace('\\', '/', $path), '/');

        if (str_contains($normalized, '..') || str_contains($normalized, "\0")) {
            return null;
        }

        foreach (['storage/app/public/', 'public/storage/', 'storage/'] as $prefix) {
            if (str_starts_with($normalized, $prefix)) {
                $normalized = substr($normalized, strlen($prefix));
                break;
            }
        }

        if (self::allowedDiskPath($normalized) && Storage::disk('public')->exists($normalized)) {
            return Storage::disk('public')->url($normalized);
        }

        if (is_file(public_path($normalized))) {
            return asset($normalized);
        }

        return null;
    }

    /**
     * Resolve a local branding image for server-side rendering without relying
     * on public/storage symlinks. Remote URLs are never fetched here.
     */
    public static function localPath(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '' || preg_match('#^(https?://|//|data:)#i', $path)) {
            return null;
        }
        $normalized = ltrim(str_replace('\\', '/', $path), '/');
        if (str_contains($normalized, '..') || str_contains($normalized, "\0")) {
            return null;
        }
        foreach (['storage/app/public/', 'public/storage/', 'storage/'] as $prefix) {
            if (str_starts_with($normalized, $prefix)) {
                $normalized = substr($normalized, strlen($prefix));
                break;
            }
        }
        if (self::allowedDiskPath($normalized) && Storage::disk('public')->exists($normalized)) {
            $local = Storage::disk('public')->path($normalized);
            return is_file($local) && is_readable($local) ? $local : null;
        }
        $local = public_path($normalized);
        return is_file($local) && is_readable($local) ? $local : null;
    }

    public static function allowedDiskPath(string $path): bool
    {
        if ($path === '' || str_contains($path, '..') || str_contains($path, "\0")) {
            return false;
        }

        $prefix = collect(self::DISK_PREFIXES)
            ->first(fn (string $candidate): bool => str_starts_with($path, $candidate));

        if ($prefix === null) {
            return false;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, $prefix === 'images/sweets-menu/'
            ? ['jpg', 'jpeg', 'png', 'webp', 'svg', 'gif', 'avif']
            : ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif', 'ico'], true);
    }
}
