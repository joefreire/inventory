<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente do Ollama Cloud.
 *
 * Dois tipos de chamada:
 * - chatJson(): modelo de chat (com imagem) que devolve um objeto JSON. Tenta
 *   os modelos da lista em ordem ate um responder JSON valido.
 * - decide(): modelo de decisao em /v1/systemone. Recebe um estado e perguntas
 *   fechadas (choice, noul, score) e devolve probabilidades. Mais de 64
 *   perguntas viram varias chamadas em paralelo.
 */
class OllamaClient
{
    /**
     * @param array<int, string> $models Modelos em ordem de preferencia
     * @param array<int, string> $images Imagens em base64 (sem prefixo data:)
     * @return array{model: string, data: array<string, mixed>}
     */
    public function chatJson(array $models, string $system, string $prompt, array $images = []): array
    {
        $erros = [];

        foreach ($models as $model) {
            $message = ['role' => 'user', 'content' => $prompt];
            if ($images) {
                $message['images'] = $images;
            }

            try {
                $response = $this->http()->post('/api/chat', [
                    'model' => $model,
                    'stream' => false,
                    'format' => 'json',
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        $message,
                    ],
                ]);
            } catch (ConnectionException $e) {
                $erros[] = "{$model}: {$e->getMessage()}";
                continue;
            }

            if ($response->failed()) {
                $erros[] = "{$model}: HTTP {$response->status()} " . mb_substr($response->body(), 0, 200);
                continue;
            }

            $data = $this->parseJson((string) $response->json('message.content'));
            if ($data === null) {
                $erros[] = "{$model}: resposta sem JSON valido";
                continue;
            }

            return ['model' => $model, 'data' => $data];
        }

        Log::warning('Ollama chat: todos os modelos falharam', ['erros' => $erros]);

        throw new AiException('Nenhum modelo de visao respondeu: ' . implode(' | ', $erros));
    }

    /**
     * Pergunta ao modelo de decisao.
     *
     * @param string|array<mixed> $state Texto ou objeto que o modelo julga
     * @param array<string, array<string, mixed>> $questions id => {type, instructions, criteria}
     * @param array<int, string> $images Imagens em base64, compartilhadas por todas as perguntas
     * @return array<string, array<string, mixed>> id => resposta (choice/noul/score)
     */
    public function decide(string|array $state, array $questions, array $images = []): array
    {
        if ($questions === []) {
            return [];
        }

        $model = config('ai.models.decision');
        $images = array_slice($images, 0, config('ai.decision_limits.images'));
        $lotes = array_chunk($questions, config('ai.decision_limits.questions'), true);

        $responses = Http::pool(function (Pool $pool) use ($lotes, $model, $state, $images) {
            foreach ($lotes as $i => $lote) {
                $body = ['model' => $model, 'state' => $state, 'questions' => $lote];
                if ($images) {
                    $body['images'] = $images;
                }
                $this->configure($pool->as((string) $i))->post('/v1/systemone', $body);
            }
        });

        $answers = [];
        foreach ($lotes as $i => $lote) {
            $response = $responses[(string) $i];

            if (!$response instanceof Response) {
                throw new AiException('Modelo de decisao sem resposta: ' . $response->getMessage());
            }
            if ($response->failed()) {
                throw new AiException(
                    "Modelo de decisao: HTTP {$response->status()} " . mb_substr($response->body(), 0, 200)
                );
            }

            $answers += (array) $response->json('answers', []);
        }

        return $answers;
    }

    private function http(): PendingRequest
    {
        return $this->configure(Http::createPendingRequest());
    }

    private function configure(PendingRequest $request): PendingRequest
    {
        $key = config('ai.ollama.api_key');
        if (!$key) {
            throw new AiException('OLLAMA_API_KEY nao configurada no .env');
        }

        return $request
            ->baseUrl(rtrim(config('ai.ollama.base_url'), '/'))
            ->withToken($key)
            ->acceptJson()
            ->timeout(config('ai.ollama.timeout'));
    }

    /**
     * Le o objeto JSON da resposta, mesmo se o modelo cercar com ```json.
     *
     * @return array<string, mixed>|null
     */
    private function parseJson(string $content): ?array
    {
        $content = trim($content);
        if (preg_match('/\{.*\}/s', $content, $m)) {
            $content = $m[0];
        }

        $data = json_decode($content, true);

        return is_array($data) ? $data : null;
    }
}
