<?php

namespace App\Ai\Ollama;

use App\Ai\Contracts\DecisionModel;
use App\Ai\Exceptions\AiException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;

/**
 * Ollama decision model (/v1/systemone). More questions than one request takes
 * become parallel requests whose answers are merged.
 */
final class OllamaDecisionModel implements DecisionModel
{
    /**
     * @param  array{questions: int, choice_options: int, images: int}  $limits
     */
    public function __construct(
        private readonly Http $http,
        private readonly OllamaHttp $ollama,
        private readonly string $model,
        private readonly array $limits,
    ) {}

    public function name(): string
    {
        return $this->model;
    }

    public function maxChoiceOptions(): int
    {
        return $this->limits['choice_options'];
    }

    public function decide(string|array $state, array $questions, array $images = []): array
    {
        if ($questions === []) {
            return [];
        }

        $images = array_slice($images, 0, $this->limits['images']);
        $batches = array_chunk($questions, $this->limits['questions'], true);

        $responses = $this->http->pool(function (Pool $pool) use ($batches, $state, $images) {
            foreach ($batches as $i => $batch) {
                $body = ['model' => $this->model, 'state' => $state, 'questions' => $batch];
                if ($images !== []) {
                    $body['images'] = $images;
                }
                $this->ollama->configure($pool->as((string) $i))->post('/v1/systemone', $body);
            }
        });

        $answers = [];
        foreach (array_keys($batches) as $i) {
            $response = $responses[(string) $i];
            if (! $response instanceof Response) {
                throw new AiException('Modelo de decisão sem resposta: '.$response->getMessage());
            }
            if ($response->failed()) {
                throw new AiException("Modelo de decisão: HTTP {$response->status()} ".mb_substr($response->body(), 0, 200));
            }
            $answers += (array) $response->json('answers', []);
        }

        return $answers;
    }
}
