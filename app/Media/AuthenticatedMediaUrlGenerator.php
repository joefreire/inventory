<?php

namespace App\Media;

use Spatie\MediaLibrary\Support\UrlGenerator\DefaultUrlGenerator;

/**
 * Part photos live on the private "media" disk: every URL goes through
 * MediaController, which requires login. (They used to be on the public disk at
 * /storage/{id}/..., sequential and readable by anyone on the internet.)
 */
class AuthenticatedMediaUrlGenerator extends DefaultUrlGenerator
{
    public function getUrl(): string
    {
        return route('media.show', [
            'media' => $this->media->getKey(),
            'conversion' => $this->conversion?->getName() ?? 'original',
        ]);
    }
}
