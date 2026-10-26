<?php

namespace App\Models\Traits;

/**
 * Trait for models that have photo media collections.
 *
 * Provides a unified method to retrieve photo URLs in different sizes
 * (thumb, preview, original) from the media library.
 *
 * @property-read string|null $main_photo_url Thumbnail URL (200x200)
 * @property-read string|null $preview_photo_url Preview URL (800x800)
 * @property-read string|null $original_photo_url Original photo URL
 */
trait HasPhotoUrls
{
    /**
     * Get the URL for a photo in the specified conversion size.
     *
     * @param  string  $conversion  The media conversion name ('thumb', 'preview', or empty for original)
     * @param  string  $collection  The media collection name
     * @return string|null The URL or null if no photo exists
     */
    public function getPhotoUrl(string $conversion = '', string $collection = 'photos'): ?string
    {
        $media = $this->getFirstMedia($collection);

        if (! $media) {
            return null;
        }

        return $conversion ? $media->getUrl($conversion) : $media->getUrl();
    }

    /**
     * Get the thumbnail photo URL (200x200).
     */
    public function getMainPhotoUrlAttribute(): ?string
    {
        return $this->getPhotoUrl('thumb');
    }

    /**
     * Get the preview photo URL (800x800).
     */
    public function getPreviewPhotoUrlAttribute(): ?string
    {
        return $this->getPhotoUrl('preview');
    }

    /**
     * Get the original photo URL.
     */
    public function getOriginalPhotoUrlAttribute(): ?string
    {
        return $this->getPhotoUrl();
    }
}
