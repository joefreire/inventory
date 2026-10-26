<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

/**
 * Photo upload rules shared by every form that takes part photos.
 *
 * Only real raster images (content is checked, not the extension), bounded size
 * and dimensions: no SVG (script inside an image) and no 100-megapixel bombs.
 * No HEIC: PHP cannot read it (getimagesize, GD). The iPhone sends JPEG to the
 * browser on its own; the front refuses a HEIC it could not convert.
 */
trait HandlesPhotoUploads
{
    /** Bigger than any phone photo; the browser already shrinks to 1600 px. */
    private static int $maxPhotoKb = 12 * 1024;

    private static int $maxPhotoSide = 8000;

    /**
     * @return array<string, mixed>
     */
    protected function photoRules(int $maxPhotos): array
    {
        $image = File::image()
            ->types(['jpg', 'jpeg', 'png', 'webp'])
            ->max(self::$maxPhotoKb)
            ->dimensions(Rule::dimensions()->maxWidth(self::$maxPhotoSide)->maxHeight(self::$maxPhotoSide));

        return [
            'photos' => ['nullable', 'array', 'max:'.$maxPhotos],
            'photos.*' => [$image],
        ];
    }

    /**
     * Uploaded photos (`photos[]`) in order.
     *
     * @return list<UploadedFile>
     */
    public function photoFiles(int $max): array
    {
        $files = array_values(array_filter((array) $this->file('photos', []), fn ($f) => $f instanceof UploadedFile));

        return array_slice($files, 0, $max);
    }
}
