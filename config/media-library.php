<?php

// Only what differs from the package defaults (vendor/spatie/laravel-medialibrary/config).
return [
    // private disk: photos are never served by the web server directly
    'disk_name' => env('MEDIA_DISK', 'media'),

    'url_generator' => App\Media\AuthenticatedMediaUrlGenerator::class,

    'max_file_size' => 1024 * 1024 * 12,
];
