<?php

namespace App\Ai\Ollama;

use App\Ai\Exceptions\AiException;
use Illuminate\Http\Client\PendingRequest;

/**
 * HTTP settings shared by the Ollama models: base URL, bearer key, timeout.
 */
final class OllamaHttp
{
    /**
     * @param  array{base_url: string, api_key: ?string, timeout: int}  $config
     */
    public function __construct(private readonly array $config) {}

    public function configure(PendingRequest $request): PendingRequest
    {
        if (empty($this->config['api_key'])) {
            throw new AiException('OLLAMA_API_KEY não configurada no .env');
        }

        return $request
            ->baseUrl(rtrim($this->config['base_url'], '/'))
            ->withToken($this->config['api_key'])
            ->acceptJson()
            ->timeout($this->config['timeout']);
    }
}
