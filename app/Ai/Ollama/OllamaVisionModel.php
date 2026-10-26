<?php

namespace App\Ai\Ollama;

use App\Ai\Contracts\VisionModel;
use App\Ai\Exceptions\AiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Facades\Log;

/**
 * Ollama chat models (/api/chat) tried in order: the next one answers when the
 * previous fails (error, 429, invalid JSON).
 */
final class OllamaVisionModel implements VisionModel
{
    /**
     * @param  list<string>  $models  in order of preference
     */
    public function __construct(
        private readonly Http $http,
        private readonly OllamaHttp $ollama,
        private readonly array $models,
    ) {}

    public function describe(string $system, string $prompt, array $images = []): array
    {
        $errors = [];

        foreach ($this->models as $model) {
            $message = ['role' => 'user', 'content' => $prompt];
            if ($images !== []) {
                $message['images'] = $images;
            }

            try {
                $response = $this->ollama->configure($this->http->createPendingRequest())->post('/api/chat', [
                    'model' => $model,
                    'stream' => false,
                    'format' => 'json',
                    'messages' => [['role' => 'system', 'content' => $system], $message],
                ]);
            } catch (ConnectionException $e) {
                $errors[] = "{$model}: {$e->getMessage()}";

                continue;
            }

            if ($response->failed()) {
                $errors[] = "{$model}: HTTP {$response->status()} ".mb_substr($response->body(), 0, 200);

                continue;
            }

            $data = self::parseJsonObject((string) $response->json('message.content'));
            if ($data === null) {
                $errors[] = "{$model}: resposta sem JSON válido";

                continue;
            }

            return ['model' => $model, 'data' => $data];
        }

        Log::warning('Ollama vision: every model failed', ['errors' => $errors]);

        throw new AiException('Nenhum modelo de visão respondeu: '.implode(' | ', $errors));
    }

    /**
     * Reads the JSON object even when the model wraps it in ```json fences.
     *
     * @return array<string, mixed>|null
     */
    private static function parseJsonObject(string $content): ?array
    {
        if (preg_match('/\{.*\}/s', $content, $match)) {
            $content = $match[0];
        }
        $data = json_decode($content, true);

        return is_array($data) ? $data : null;
    }
}
