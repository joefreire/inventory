<?php

namespace App\Services;

use App\Models\Item;
use Illuminate\Http\UploadedFile;

/**
 * Service class for managing inventory items.
 *
 * Handles business logic for item CRUD operations including
 * tag synchronization and media (photo) management.
 */
class ItemService
{
    /**
     * Create a new item with optional tags and photo.
     *
     * @param array<string, mixed> $data Validated item data
     * @param array<string> $tagNames Array of tag names to associate
     * @param UploadedFile|null $photo Photo file to upload
     * @return Item The created item
     */
    public function create(array $data, array $tagNames = [], ?UploadedFile $photo = null): Item
    {
        $item = Item::create($data);

        if (!empty($tagNames)) {
            $item->syncTags($tagNames);
        }

        if ($photo) {
            $this->uploadPhoto($item, $photo);
        }

        return $item;
    }

    /**
     * Update an existing item with optional tags and photo changes.
     *
     * @param Item $item The item to update
     * @param array<string, mixed> $data Validated item data
     * @param array<string>|null $tagNames Array of tag names (null to keep existing)
     * @param UploadedFile|null $photo New photo file to upload
     * @param bool $removePhoto Whether to remove the existing photo
     * @return Item The updated item
     */
    public function update(
        Item $item,
        array $data,
        ?array $tagNames = null,
        ?UploadedFile $photo = null,
        bool $removePhoto = false
    ): Item {
        $item->update($data);

        if ($tagNames !== null) {
            $item->syncTags($tagNames);
        }

        if ($removePhoto || $photo) {
            $item->clearMediaCollection('photos');
        }

        if ($photo) {
            $this->uploadPhoto($item, $photo);
        }

        return $item;
    }

    /**
     * Delete an item and its associated media.
     *
     * @param Item $item The item to delete
     * @return bool True on success
     */
    public function delete(Item $item): bool
    {
        return $item->delete();
    }

    /**
     * Move an item to a different location.
     *
     * @param Item $item The item to move
     * @param int|null $locationId The new location ID (null for no location)
     * @return Item The updated item
     */
    public function move(Item $item, ?int $locationId): Item
    {
        $item->update(['location_id' => $locationId]);

        return $item;
    }

    /**
     * Upload a photo to an item's media collection.
     *
     * @param Item $item The item to attach photo to
     * @param UploadedFile $photo The photo file
     * @return void
     */
    private function uploadPhoto(Item $item, UploadedFile $photo): void
    {
        $item->addMedia($photo)->toMediaCollection('photos');
    }
}
