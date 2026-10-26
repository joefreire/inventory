<?php

namespace App\Http\Controllers;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves part photos from the private disk, only to logged-in users.
 */
class MediaController extends Controller
{
    public const CONVERSIONS = ['original', 'thumb', 'preview'];

    public function show(Media $media, string $conversion): BinaryFileResponse
    {
        $path = $conversion === 'original' ? $media->getPath() : $media->getPath($conversion);
        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Cache-Control' => 'private, max-age=604800',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
