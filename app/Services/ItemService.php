<?php

namespace App\Services;

use App\Models\Item;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Writes on parts: create/update with tags and photos, move, quantity.
 */
class ItemService
{
    /**
     * @param  array<string, mixed>  $data  validated attributes
     * @param  list<string>  $tagNames
     * @param  list<UploadedFile>  $photos  in display order (first = main)
     */
    public function create(array $data, array $tagNames = [], array $photos = []): Item
    {
        return DB::transaction(function () use ($data, $tagNames, $photos) {
            $item = Item::create($data);
            $item->syncTags($tagNames);
            $this->addPhotos($item, $photos);

            return $item;
        });
    }

    /**
     * @param  array<string, mixed>  $data  validated attributes
     * @param  list<string>|null  $tagNames  null keeps the current tags
     * @param  list<UploadedFile>  $newPhotos  added after the current ones
     * @param  list<int>  $removeMediaIds  only this item's photos are removed
     */
    public function update(Item $item, array $data, ?array $tagNames = null, array $newPhotos = [], array $removeMediaIds = []): Item
    {
        DB::transaction(function () use ($item, $data, $tagNames, $newPhotos) {
            $item->update($data);
            if ($tagNames !== null) {
                $item->syncTags($tagNames);
            }
            $this->addPhotos($item, $newPhotos);
        });

        // Deleting a media erases its files at once, and a rollback does not bring
        // them back: photos only go after everything else was saved.
        if ($removeMediaIds !== []) {
            $item->getMedia('photos')->whereIn('id', $removeMediaIds)->each->delete();
            $item->unsetRelation('media');
        }

        return $item;
    }

    public function delete(Item $item): bool
    {
        return (bool) $item->delete();
    }

    public function move(Item $item, ?int $locationId): Item
    {
        $item->update(['location_id' => $locationId]);

        return $item;
    }

    /**
     * Adds or removes units in one atomic UPDATE (two quick taps on + never
     * overwrite each other) and never goes below zero.
     */
    public function adjustQuantity(Item $item, int $delta): int
    {
        Item::whereKey($item->getKey())->update([
            'quantity' => DB::raw('CASE WHEN quantity + '.(int) $delta.' < 0 THEN 0 ELSE quantity + '.(int) $delta.' END'),
            'updated_at' => now(),
        ]);

        return (int) $item->refresh()->quantity;
    }

    /**
     * @param  list<UploadedFile>  $photos
     */
    private function addPhotos(Item $item, array $photos): void
    {
        $added = [];
        try {
            foreach ($photos as $photo) {
                $added[] = $item->addMedia($photo)->toMediaCollection('photos');
            }
        } catch (Throwable $e) {
            // the rollback undoes the rows, not the files already written to disk
            foreach ($added as $media) {
                $media->delete();
            }
            throw $e;
        }
    }
}
