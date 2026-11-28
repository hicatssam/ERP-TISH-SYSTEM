<?php

namespace App\Services\Restaurant;

use App\Support\PublicImageUrl;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Optional, non-destructive transparent artwork cache for the public Tish menu.
 * Original product photos remain untouched; no database columns are required.
 */
class MenuCutoutService
{
    public function enabled(): bool
    {
        return (bool) config('menu_cutouts.enabled', false);
    }

    public function outputPath(string $source): string
    {
        return 'menu-cutouts/'.sha1(trim(str_replace('\\', '/', $source))).'.png';
    }

    public function sourceFile(?string $source): ?string
    {
        if (! is_string($source) || trim($source) === '') {
            return null;
        }

        // Only images already on our server. Never fetch arbitrary image URLs.
        $file = PublicImageUrl::localPath($source);
        if (! $file || ! is_file($file) || ! is_readable($file)) {
            return null;
        }

        $real = realpath($file);
        $publicRoot = realpath(public_path());
        $storageRoot = realpath(Storage::disk('public')->path(''));
        if (! $real || ! (($publicRoot && str_starts_with($real, $publicRoot.DIRECTORY_SEPARATOR))
            || ($storageRoot && str_starts_with($real, $storageRoot.DIRECTORY_SEPARATOR)))) {
            return null;
        }

        if (! in_array(strtolower(pathinfo($real, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return null;
        }

        if (@filesize($real) > 12 * 1024 * 1024) {
            return null;
        }

        $info = @getimagesize($real);
        if (! $info || ! in_array($info['mime'] ?? '', ['image/jpeg', 'image/png', 'image/webp'], true)
            || ($info[0] * $info[1]) > 30000000) {
            return null;
        }

        return $real;
    }

    public function needsGeneration(?string $source, bool $force = false): bool
    {
        if (! $this->enabled() || ! $this->sourceFile($source)) {
            return false;
        }

        return $force || $this->cutoutUrl($source) === null;
    }

    public function cutoutUrl(?string $source): ?string
    {
        if (! $this->enabled() || ! $source) {
            return null;
        }

        $original = $this->sourceFile($source);
        if (! $original) {
            return null;
        }

        $path = $this->outputPath($source);
        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            return null;
        }

        $converted = $disk->path($path);
        if (! is_file($converted) || @filesize($converted) < 100 || @filemtime($converted) < @filemtime($original)) {
            return null;
        }

        // Optional offline-generated WebP derivative. The PNG is authoritative
        // and its timestamp/size version the derivative and its browser URL.
        // Never encode or resize during a customer request.
        $version = @filemtime($converted).'-'.@filesize($converted);
        $webp = substr($path, 0, -4).'.v1-'.$version.'.webp';
        $webpFile = $disk->path($webp);
        if (is_file($webpFile) && is_readable($webpFile)
            && @filesize($webpFile) > 0
            && @filesize($webpFile) < @filesize($converted)
            && @filemtime($webpFile) >= @filemtime($converted)) {
            return PublicImageUrl::url($webp) ?: PublicImageUrl::url($path);
        }

        // Use the already-secured image route; no storage symlink is necessary.
        return PublicImageUrl::url($path);
    }

    public function generate(string $source, bool $force = false): bool
    {
        if (! $this->needsGeneration($source, $force)) {
            return false;
        }

        $original = $this->sourceFile($source);
        if (! $original) {
            return false;
        }

        $disk = Storage::disk('public');
        $disk->makeDirectory('menu-cutouts');
        $target = $disk->path($this->outputPath($source));
        $temp = $target.'.'.bin2hex(random_bytes(8)).'.png';

        try {
            $process = new Process([
                (string) config('menu_cutouts.python', base_path('.venv-menu-cutouts/bin/python')),
                base_path('scripts/remove_menu_background.py'),
                '--input', $original,
                '--output', $temp,
                '--model', (string) config('menu_cutouts.model', 'u2net'),
            ]);
            $process->setTimeout((int) config('menu_cutouts.timeout', 360));
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException('Background removal failed: '.mb_substr($process->getErrorOutput(), 0, 800));
            }

            $info = is_file($temp) ? @getimagesize($temp) : false;
            if (! $info || ($info['mime'] ?? '') !== 'image/png' || @filesize($temp) < 100) {
                throw new RuntimeException('Background removal returned an invalid PNG.');
            }

            if (! @rename($temp, $target)) {
                throw new RuntimeException('Unable to save processed image.');
            }

            return true;
        } finally {
            if (is_file($temp)) {
                @unlink($temp);
            }
        }
    }
}
