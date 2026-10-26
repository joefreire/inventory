<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\File;

/**
 * Photo upload rules shared by every form that takes part photos.
 *
 * Only real raster images (content is checked, not the extension), bounded size
 * and dimensions: no SVG (script inside an image) and no 100-megapixel bombs.
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
            ->types(['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif'])
            ->max(self::$maxPhotoKb)
            ->dimensions(\Illuminate\Validation\Rule::dimensions()->maxWidth(self::$maxPhotoSide)->maxHeight(self::$maxPhotoSide));

        return [
            'photo' => ['nullable', $image],
            'photos' => ['nullable', 'array', 'max:'.$maxPhotos],
            'photos.*' => [$image],
        ];
    }

    /**
     * Uploaded photos in order: `photos[]` and, for older clients, `photo` first.
     *
     * @return list<UploadedFile>
     */
    public function photoFiles(int $max): array
    {
        $files = array_values(array_filter((array) $this->file('photos', []), fn ($f) => $f instanceof UploadedFile));
        if ($this->file('photo') instanceof UploadedFile) {
            array_unshift($files, $this->file('photo'));
        }

        return array_slice($files, 0, $max);
    }
}
