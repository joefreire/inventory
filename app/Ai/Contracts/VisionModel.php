<?php

namespace App\Ai\Contracts;

use App\Ai\Exceptions\AiException;

/**
 * A chat model that reads images and answers with a JSON object.
 */
interface VisionModel
{
    /**
     * @param  list<string>  $images  base64 images (no data: prefix)
     * @return array{model: string, data: array<string, mixed>}
     *
     * @throws AiException
     */
    public function describe(string $system, string $prompt, array $images = []): array;
}
