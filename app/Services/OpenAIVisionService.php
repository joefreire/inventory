<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

/**
 * Service for analyzing images using OpenAI Vision API.
 *
 * Uses GPT-4 Vision to identify electronic components and technical parts,
 * returning structured data including name, description, category, and specs.
 */
class OpenAIVisionService
{
    /**
     * System prompt for the AI model.
     *
     * @var string
     */
    private string $systemPrompt = <<<'PROMPT'
Voce e um especialista em identificacao de componentes eletronicos e pecas tecnicas.
Analise a imagem fornecida e retorne APENAS um JSON valido (sem markdown, sem explicacoes) com a seguinte estrutura:

{
    "name": "nome tecnico do componente (ex: Capacitor Eletrolitico 100uF 25V)",
    "description": "descricao breve em portugues, max 150 caracteres",
    "category": "categoria principal (resistor, capacitor, ic, conector, modulo, sensor, led, transistor, diodo, indutor, rele, fusivel, cristal, outro)",
    "suggested_tags": ["array", "de", "tags", "relevantes"],
    "specs": {
        "valor": "se aplicavel (ex: 100uF, 10k ohm)",
        "tensao": "se aplicavel (ex: 25V)",
        "potencia": "se aplicavel (ex: 1W)",
        "encapsulamento": "se visivel (ex: DIP-8, SMD 0805, TO-220)",
        "marca": "se visivel",
        "modelo": "se visivel"
    },
    "confidence": 0.0 a 1.0
}

Se nao conseguir identificar o componente, retorne:
{
    "name": "Componente nao identificado",
    "description": "Nao foi possivel identificar o componente na imagem",
    "category": "outro",
    "suggested_tags": [],
    "specs": {},
    "confidence": 0.0
}
PROMPT;

    /**
     * Analyze an image file and identify the component.
     *
     * @param string $imagePath Absolute path to the image file
     * @return array{name: string, description: string, category: string, suggested_tags: array<string>, specs: array<string, mixed>, confidence: float}
     */
    public function analyzeImage(string $imagePath): array
    {
        try {
            $imageData = $this->getImageData($imagePath);

            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->systemPrompt,
                    ],
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => $imageData,
                                    'detail' => 'high',
                                ],
                            ],
                            [
                                'type' => 'text',
                                'text' => 'Identifique este componente/peca e retorne o JSON.',
                            ],
                        ],
                    ],
                ],
                'max_tokens' => 1000,
            ]);

            $content = $response->choices[0]->message->content;

            $content = preg_replace('/```json\s*/i', '', $content);
            $content = preg_replace('/```\s*$/i', '', $content);
            $content = trim($content);

            $result = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('OpenAI Vision: Invalid JSON response', [
                    'content' => $content,
                    'error' => json_last_error_msg(),
                ]);

                return $this->getDefaultResponse();
            }

            return $this->normalizeResponse($result);
        } catch (\Exception $e) {
            Log::error('OpenAI Vision: Error analyzing image', [
                'error' => $e->getMessage(),
                'image_path' => $imagePath,
            ]);

            return $this->getDefaultResponse();
        }
    }

    /**
     * Analyze an uploaded file.
     *
     * @param UploadedFile $file The uploaded image file
     * @return array{name: string, description: string, category: string, suggested_tags: array<string>, specs: array<string, mixed>, confidence: float}
     */
    public function analyzeUploadedFile(UploadedFile $file): array
    {
        $tempPath = $file->getRealPath();

        return $this->analyzeImage($tempPath);
    }

    /**
     * Convert image file to base64 data URL.
     *
     * @param string $imagePath Path to the image file
     * @return string Data URL with base64-encoded image
     */
    private function getImageData(string $imagePath): string
    {
        $imageContent = file_get_contents($imagePath);
        $mimeType = $this->getMimeType($imagePath);
        $base64 = base64_encode($imageContent);

        return "data:{$mimeType};base64,{$base64}";
    }

    /**
     * Get the MIME type for an image file.
     *
     * @param string $path Path to the image file
     * @return string MIME type string
     */
    private function getMimeType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }

    /**
     * Normalize the API response to ensure consistent structure.
     *
     * @param array<string, mixed> $result Raw API response
     * @return array{name: string, description: string, category: string, suggested_tags: array<string>, specs: array<string, mixed>, confidence: float}
     */
    private function normalizeResponse(array $result): array
    {
        return [
            'name' => $result['name'] ?? 'Item sem nome',
            'description' => $result['description'] ?? '',
            'category' => strtolower($result['category'] ?? 'outro'),
            'suggested_tags' => $result['suggested_tags'] ?? [],
            'specs' => $result['specs'] ?? [],
            'confidence' => (float) ($result['confidence'] ?? 0.5),
        ];
    }

    /**
     * Get the default response for unidentified items.
     *
     * @return array{name: string, description: string, category: string, suggested_tags: array<string>, specs: array<string, mixed>, confidence: float}
     */
    private function getDefaultResponse(): array
    {
        return [
            'name' => 'Item nao identificado',
            'description' => 'Nao foi possivel identificar automaticamente',
            'category' => 'outro',
            'suggested_tags' => [],
            'specs' => [],
            'confidence' => 0.0,
        ];
    }
}
