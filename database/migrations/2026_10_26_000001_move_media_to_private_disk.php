<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Part photos were on the public disk (storage/app/public/{id}, served at
 * /storage/{id}/... without login). Moves them to the private "media" disk.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->move('public', 'media', storage_path('app/public'), storage_path('app/media'));
    }

    public function down(): void
    {
        $this->move('media', 'public', storage_path('app/media'), storage_path('app/public'));
    }

    private function move(string $from, string $to, string $fromRoot, string $toRoot): void
    {
        File::ensureDirectoryExists($toRoot);

        foreach (DB::table('media')->where('disk', $from)->pluck('id') as $id) {
            $source = $fromRoot.DIRECTORY_SEPARATOR.$id;
            $target = $toRoot.DIRECTORY_SEPARATOR.$id;
            if (is_dir($source) && ! is_dir($target)) {
                File::moveDirectory($source, $target);
            }
            DB::table('media')->where('id', $id)->update(['disk' => $to, 'conversions_disk' => $to]);
        }
    }
};
