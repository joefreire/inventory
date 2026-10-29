<?php

namespace App\Providers;

use App\Ai\Contracts\DecisionModel;
use App\Ai\Contracts\VisionModel;
use App\Ai\Decisions\CategoryDecision;
use App\Ai\Decisions\DuplicateCandidates;
use App\Ai\Decisions\DuplicateDecision;
use App\Ai\Decisions\LocationDecision;
use App\Ai\Decisions\PurchaseDecision;
use App\Ai\Decisions\TagsDecision;
use App\Ai\Identification\PartIdentifier;
use App\Ai\ItemAdvisor;
use App\Ai\Ollama\OllamaDecisionModel;
use App\Ai\Ollama\OllamaHttp;
use App\Ai\Ollama\OllamaVisionModel;
use App\Ai\Search\NaturalSearch;
use App\Purchases\PurchaseItemEnricher;
use App\Tags\TagNormalizer;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the AI layer. The rest of the app depends on the contracts
 * (VisionModel, DecisionModel); swapping provider = binding another class here.
 */
class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OllamaHttp::class, fn (Application $app) => new OllamaHttp([
            'base_url' => (string) $app['config']->get('ai.ollama.base_url'),
            'api_key' => $app['config']->get('ai.ollama.api_key'),
            'timeout' => (int) $app['config']->get('ai.ollama.timeout'),
        ]));

        $this->app->bind(VisionModel::class, fn (Application $app) => new OllamaVisionModel(
            $app->make(Http::class),
            $app->make(OllamaHttp::class),
            array_values((array) $app['config']->get('ai.models.vision')),
        ));

        $this->app->bind(DecisionModel::class, fn (Application $app) => new OllamaDecisionModel(
            $app->make(Http::class),
            $app->make(OllamaHttp::class),
            (string) $app['config']->get('ai.models.decision'),
            (array) $app['config']->get('ai.decision_limits'),
        ));

        $this->app->singleton(TagNormalizer::class, fn (Application $app) => new TagNormalizer(
            array_values((array) $app['config']->get('ai.generic_tags', []))
        ));

        $this->app->bind(PartIdentifier::class, fn (Application $app) => new PartIdentifier(
            $app->make(VisionModel::class),
            $app->make(TagNormalizer::class),
            resource_path('prompts/identify-part.md'),
        ));

        $this->app->bind(PurchaseItemEnricher::class, fn (Application $app) => new PurchaseItemEnricher(
            $app->make(VisionModel::class),
            resource_path('prompts/complete-purchase-item.md'),
        ));

        // decisions: one new instance per analysis (they keep state between questions and answers)
        $this->app->bind(CategoryDecision::class, fn (Application $app) => new CategoryDecision(
            (array) $app['config']->get('ai.categories')
        ));
        $this->app->bind(TagsDecision::class, fn (Application $app) => new TagsDecision(
            $app->make(TagNormalizer::class),
            (float) $app['config']->get('ai.thresholds.tag'),
            (int) $app['config']->get('ai.max_tags', 5),
        ));
        $this->app->bind(LocationDecision::class, fn (Application $app) => new LocationDecision(
            $app->make(DecisionModel::class),
            (float) $app['config']->get('ai.thresholds.location'),
        ));
        $this->app->bind(DuplicateDecision::class, fn (Application $app) => new DuplicateDecision(
            $app->make(DecisionModel::class),
            $app->make(DuplicateCandidates::class),
            (float) $app['config']->get('ai.thresholds.duplicate'),
        ));
        $this->app->bind(PurchaseDecision::class, fn (Application $app) => new PurchaseDecision(
            $app->make(DecisionModel::class),
            (float) $app['config']->get('ai.thresholds.purchase'),
        ));

        $this->app->bind(ItemAdvisor::class, fn (Application $app) => new ItemAdvisor(
            $app->make(PartIdentifier::class),
            $app->make(DecisionModel::class),
            $app,
            array_values((array) $app['config']->get('ai.decisions')),
        ));

        $this->app->bind(NaturalSearch::class, fn (Application $app) => new NaturalSearch(
            $app->make(DecisionModel::class),
            $app->make('cache.store'),
            (float) $app['config']->get('ai.thresholds.search'),
            (int) $app['config']->get('ai.search.max_items', 500),
            (int) $app['config']->get('ai.search.cache_minutes', 10),
        ));
    }
}
