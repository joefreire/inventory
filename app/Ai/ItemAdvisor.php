<?php

namespace App\Ai;

use App\Ai\Contracts\DecisionModel;
use App\Ai\Decisions\PartDecision;
use App\Ai\Exceptions\AiException;
use App\Ai\Identification\PartIdentifier;
use Illuminate\Contracts\Container\Container;

/**
 * Suggests how to register a part: identifies it (photos and/or what was typed),
 * then asks every configured decision (config ai.decisions) in one request to
 * the decision model.
 */
final class ItemAdvisor
{
    /** Photos sent to the AI: image limit of the decision model in the cloud. */
    public const MAX_PHOTOS = 4;

    /**
     * @param  list<class-string<PartDecision>>  $decisions
     */
    public function __construct(
        private readonly PartIdentifier $identifier,
        private readonly DecisionModel $decisionModel,
        private readonly Container $container,
        private readonly array $decisions,
    ) {}

    /**
     * @param  list<string>  $imagePaths  photos of the same part
     * @return array<string, mixed> JSON-ready suggestion
     *
     * @throws AiException
     */
    public function advise(array $imagePaths, string $typedName = '', string $typedDescription = ''): array
    {
        $images = array_map(
            fn (string $path) => base64_encode((string) file_get_contents($path)),
            array_slice(array_values(array_filter($imagePaths)), 0, self::MAX_PHOTOS)
        );

        $part = $this->identifier->identify($images, $typedName, $typedDescription);
        if ($part->isEmpty()) {
            throw new AiException('Mande uma foto ou digite o nome da peça.');
        }

        /** @var list<PartDecision> $decisions */
        $decisions = array_map(fn (string $class) => $this->container->make($class), $this->decisions);

        $questions = [];
        foreach ($decisions as $decision) {
            $questions += $decision->questions($part);
        }
        $answers = $this->decisionModel->decide($part->toDecisionState(), $questions, $part->images);

        $result = [
            'name' => $part->name,
            'description' => $part->description,
            'specs' => $part->specs,
            'visible_text' => $part->visibleText,
            'quantity' => $part->quantity,
            'kit' => $part->isKit,
        ];
        foreach ($decisions as $decision) {
            $result[$decision->key()] = $decision->interpret($answers);
        }

        return $result + [
            'models' => ['vision' => $part->visionModel, 'decision' => $this->decisionModel->name()],
            'photos_used' => count($images),
        ];
    }
}
