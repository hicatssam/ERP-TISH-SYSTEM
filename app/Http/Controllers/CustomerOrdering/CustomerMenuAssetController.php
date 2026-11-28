<?php

namespace App\Http\Controllers\CustomerOrdering;

use App\Http\Controllers\Controller;
use App\Support\PublicImageUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerMenuAssetController extends Controller
{
    public function show(
        Request $request,
        string $path
    ): StreamedResponse {
        $path = ltrim(
            str_replace('\\\\', '/', $path),
            '/'
        );

        // Extensionless alias avoids static-file location rules in Nginx.
        // Only the exact versioned cutout naming scheme may use this alias.
        if (preg_match('#^menu-cutouts/([a-f0-9]{40}\.v1-[0-9]+-[0-9]+)/webp$#D', $path, $match)) {
            $path = 'menu-cutouts/'.$match[1].'.webp';
        }

        abort_unless(PublicImageUrl::allowedDiskPath($path), 404);

        $disk = Storage::disk('public');

        abort_unless(
            $disk->exists($path),
            404
        );

        return $disk->response(
            $path,
            basename($path),
            [
                'Cache-Control' =>
                    'public, max-age=86400',
                'X-Content-Type-Options' =>
                    'nosniff',
            ]
        );
    }
}
