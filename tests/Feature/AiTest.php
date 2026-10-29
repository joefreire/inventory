<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Location;
use App\Models\PurchaseItem;
use App\Models\PurchaseOrder;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai.ollama.api_key' => 'test-key',
            'ai.ollama.base_url' => 'https://ollama.test',
            'ai.models.vision' => ['visao-a', 'visao-b'],
            'ai.models.decision' => 'clef',
        ]);
    }

    private function chatResponse(array $data): array
    {
        return ['message' => ['role' => 'assistant', 'content' => json_encode($data)]];
    }

    public function test_analyze_with_photo_uses_vision_then_decision(): void
    {
        $caixa = Location::create(['name' => 'Caixa sensores']);
        Location::create(['name' => 'Gaveta resistores']);
        $existente = Item::create(['name' => 'Sensor DS18B20', 'location_id' => $caixa->id, 'quantity' => 4]);
        $tag = Tag::create(['name' => 'sensor']);

        Http::fake([
            'ollama.test/api/chat' => Http::response($this->chatResponse([
                'name' => 'Sensor de temperatura DS18B20',
                'description' => 'Sensor digital 1-wire',
                'specs' => ['tensao' => '3-5V', 'vazio' => ''],
                'visible_text' => 'DALLAS 18B20',
                'tags' => ['Sensor', 'temperatura'],
            ])),
            'ollama.test/v1/systemone' => Http::response(['answers' => [
                'categoria' => ['type' => 'choice', 'choice' => 'sensor', 'probabilities' => ['sensor' => 0.97]],
                "tag_{$tag->id}" => ['type' => 'noul', 'noul' => 0.95],
                'local' => ['type' => 'choice', 'choice' => "l{$caixa->id}", 'probabilities' => ["l{$caixa->id}" => 0.9, 'nenhum' => 0.05]],
                'duplicado' => ['type' => 'choice', 'choice' => "i{$existente->id}", 'probabilities' => ["i{$existente->id}" => 0.88]],
            ]]),
        ]);

        $response = $this->postJson(route('items.analyze'), [
            'photos' => [UploadedFile::fake()->image('peca.jpg')],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Sensor de temperatura DS18B20')
            ->assertJsonPath('data.specs', ['tensao' => '3-5V'])
            ->assertJsonPath('data.category.value', 'sensor')
            ->assertJsonPath('data.location.suggested.id', $caixa->id)
            ->assertJsonPath('data.duplicate.id', $existente->id)
            ->assertJsonPath('data.models.vision', 'visao-a');

        // tag existente aprovada + so a sugestao que nao existe ("Sensor" = "sensor")
        $this->assertSame(
            [['sensor', true], ['temperatura', false]],
            array_map(fn ($t) => [$t['name'], $t['existing']], $response->json('data.tags'))
        );

        Http::assertSent(function (Request $request) {
            if (! str_ends_with($request->url(), '/v1/systemone')) {
                return false;
            }

            return $request['model'] === 'clef'
                && count($request['images']) === 1
                && isset($request['questions']['categoria'], $request['questions']['local'], $request['questions']['duplicado'])
                && $request->hasHeader('Authorization', 'Bearer test-key');
        });
    }

    public function test_vision_falls_back_to_next_model(): void
    {
        Http::fake([
            'ollama.test/api/chat' => function (Request $request) {
                return $request['model'] === 'visao-a'
                    ? Http::response(['error' => 'rate limited'], 429)
                    : Http::response($this->chatResponse(['name' => 'Rele 5V']));
            },
            'ollama.test/v1/systemone' => Http::response(['answers' => [
                'categoria' => ['type' => 'choice', 'choice' => 'rele', 'probabilities' => ['rele' => 0.8]],
            ]]),
        ]);

        $this->postJson(route('items.analyze'), ['photos' => [UploadedFile::fake()->image('rele.jpg')]])
            ->assertOk()
            ->assertJsonPath('data.name', 'Rele 5V')
            ->assertJsonPath('data.models.vision', 'visao-b')
            ->assertJsonPath('data.duplicate', null)
            ->assertJsonPath('data.location', null);
    }

    public function test_analyze_without_photo_uses_typed_name_and_skips_vision(): void
    {
        Http::fake([
            'ollama.test/v1/systemone' => Http::response(['answers' => [
                'categoria' => ['type' => 'choice', 'choice' => 'resistor', 'probabilities' => ['resistor' => 0.99]],
            ]]),
        ]);

        $this->postJson(route('items.analyze'), ['name' => 'Resistor 10k'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Resistor 10k')
            ->assertJsonPath('data.models.vision', null);

        Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/api/chat'));
    }

    public function test_low_probability_duplicate_and_location_are_not_suggested(): void
    {
        $caixa = Location::create(['name' => 'Caixa']);
        $item = Item::create(['name' => 'Outra coisa', 'location_id' => $caixa->id]);

        Http::fake([
            'ollama.test/v1/systemone' => Http::response(['answers' => [
                'local' => ['type' => 'choice', 'choice' => "l{$caixa->id}", 'probabilities' => ["l{$caixa->id}" => 0.3, 'nenhum' => 0.7]],
                'duplicado' => ['type' => 'choice', 'choice' => "i{$item->id}", 'probabilities' => ["i{$item->id}" => 0.4]],
            ]]),
        ]);

        $this->postJson(route('items.analyze'), ['name' => 'Motor de passo'])
            ->assertOk()
            ->assertJsonPath('data.duplicate', null)
            ->assertJsonPath('data.location.suggested', null)
            ->assertJsonPath('data.location.options.0.id', $caixa->id);
    }

    public function test_analyze_requires_photo_or_name(): void
    {
        $this->postJson(route('items.analyze'), [])->assertStatus(422);
    }

    public function test_analyze_reports_missing_key(): void
    {
        config(['ai.ollama.api_key' => null]);
        Http::fake();

        $this->postJson(route('items.analyze'), ['name' => 'Resistor'])
            ->assertStatus(502)
            ->assertJsonPath('success', false);
    }

    public function test_analyze_reports_when_every_vision_model_fails(): void
    {
        Http::fake(['ollama.test/api/chat' => Http::response('erro', 500)]);

        $this->postJson(route('items.analyze'), ['photos' => [UploadedFile::fake()->image('x.jpg')]])
            ->assertStatus(502)
            ->assertJsonPath('success', false);
    }

    public function test_natural_search_ranks_items_and_splits_in_batches(): void
    {
        $items = collect(range(1, 70))->map(fn ($i) => Item::create(['name' => "Item {$i}"]));
        $sensor = $items[10];
        $outro = $items[65];

        Http::fake(function (Request $request) use ($sensor, $outro) {
            $answers = [];
            foreach (array_keys($request['questions']) as $id) {
                $p = match ($id) {
                    "i{$sensor->id}" => 0.9,
                    "i{$outro->id}" => 0.6,
                    default => 0.1,
                };
                $answers[$id] = ['type' => 'noul', 'noul' => $p];
            }

            return Http::response(['answers' => $answers]);
        });

        $response = $this->get(route('items.ask', ['q' => 'medir temperatura']));

        $response->assertOk();
        $this->assertSame(
            [$sensor->id, $outro->id],
            $response->viewData('results')->map(fn ($r) => $r['item']->id)->all()
        );
        Http::assertSentCount(2); // 70 perguntas = 64 + 6
    }

    public function test_ask_page_without_question_does_not_call_ai(): void
    {
        Http::fake();

        $this->get(route('items.ask'))->assertOk()->assertSee('Descreva o que precisa');

        Http::assertNothingSent();
    }

    public function test_vision_quantity_kit_spec_keys_and_generic_tags(): void
    {
        $generica = Tag::create(['name' => 'componente']);
        $util = Tag::create(['name' => 'lipo']);

        Http::fake([
            'ollama.test/api/chat' => Http::response($this->chatResponse([
                'name' => 'Conector XT60',
                'quantity' => '20',
                'kit' => false,
                'specs' => ['Tensão Bobina' => '5V', 'modelo' => 'XT60'],
                'tags' => ['Elétrica', 'xt60', 'componente'],
            ])),
            'ollama.test/v1/systemone' => function (Request $request) use ($generica, $util) {
                // a tag generica nem vira pergunta
                $this->assertArrayNotHasKey("tag_{$generica->id}", $request['questions']);

                return Http::response(['answers' => ["tag_{$util->id}" => ['type' => 'noul', 'noul' => 0.9]]]);
            },
        ]);

        $r = $this->postJson(route('items.analyze'), ['photos' => [UploadedFile::fake()->image('xt60.jpg')]])->assertOk();

        $r->assertJsonPath('data.quantity', 20)
            ->assertJsonPath('data.kit', false)
            ->assertJsonPath('data.specs', ['tensao_bobina' => '5V', 'modelo' => 'XT60']);
        $this->assertSame(['lipo', 'xt60'], array_column($r->json('data.tags'), 'name'));
    }

    public function test_invalid_vision_quantity_is_ignored(): void
    {
        Http::fake([
            'ollama.test/api/chat' => Http::response($this->chatResponse(['name' => 'Kit resistores', 'quantity' => 'muitos', 'kit' => true])),
            'ollama.test/v1/systemone' => Http::response(['answers' => []]),
        ]);

        $this->postJson(route('items.analyze'), ['photos' => [UploadedFile::fake()->image('k.jpg')]])
            ->assertOk()
            ->assertJsonPath('data.quantity', null)
            ->assertJsonPath('data.kit', true);
    }

    public function test_several_photos_go_together_to_both_models(): void
    {
        Http::fake([
            'ollama.test/api/chat' => Http::response($this->chatResponse(['name' => 'Rele 5V JQC-3F'])),
            'ollama.test/v1/systemone' => Http::response(['answers' => []]),
        ]);

        $fotos = array_map(fn ($i) => UploadedFile::fake()->image("f{$i}.jpg"), range(1, 4));
        $this->postJson(route('items.analyze'), ['photos' => $fotos])
            ->assertOk()
            ->assertJsonPath('data.photos_used', 4);

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/api/chat')
            && count($r['messages'][1]['images']) === 4
            && str_contains($r['messages'][1]['content'], 'MESMA peça'));
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/v1/systemone') && count($r['images']) === 4);
    }

    public function test_more_than_four_photos_for_ai_is_rejected(): void
    {
        Http::fake();
        $fotos = array_map(fn ($i) => UploadedFile::fake()->image("f{$i}.jpg"), range(1, 5));

        $this->postJson(route('items.analyze'), ['photos' => $fotos])->assertStatus(422);
        Http::assertNothingSent();
    }

    public function test_empty_vision_answer_falls_back_to_next_model(): void
    {
        Http::fake([
            'ollama.test/api/chat' => function (Request $request) {
                return $request['model'] === 'visao-a'
                    ? Http::response($this->chatResponse(['name' => '', 'description' => '']))
                    : Http::response($this->chatResponse(['name' => 'Servo MG996R']));
            },
            'ollama.test/v1/systemone' => Http::response(['answers' => []]),
        ]);

        $this->postJson(route('items.analyze'), ['photos' => [UploadedFile::fake()->image('servo.jpg')]])
            ->assertOk()
            ->assertJsonPath('data.name', 'Servo MG996R')
            ->assertJsonPath('data.models.vision', 'visao-b');
    }

    public function test_decision_without_answers_is_an_error_not_an_empty_suggestion(): void
    {
        Http::fake(['ollama.test/v1/systemone' => Http::response(['error' => 'model overloaded'])]);

        $this->postJson(route('items.analyze'), ['name' => 'Resistor 10k'])
            ->assertStatus(502)
            ->assertJsonPath('success', false);
    }

    public function test_search_failure_is_not_cached_as_nothing_found(): void
    {
        Item::create(['name' => 'Sensor DS18B20']);
        Http::fakeSequence('ollama.test/*')
            ->push(['error' => 'model overloaded'])
            ->push(['answers' => []]);

        $this->get(route('items.ask', ['q' => 'medir temperatura']))->assertOk();
        $this->get(route('items.ask', ['q' => 'medir temperatura']))->assertOk();

        Http::assertSentCount(2); // the failure was not remembered
    }

    public function test_search_cache_sees_a_tag_only_change(): void
    {
        $item = Item::create(['name' => 'Sensor']);
        Http::fake(['ollama.test/*' => Http::response(['answers' => []])]);

        $this->get(route('items.ask', ['q' => 'medir temperatura']))->assertOk();
        $item->syncTags(['ds18b20']); // does not change items.updated_at
        $this->get(route('items.ask', ['q' => 'medir temperatura']))->assertOk();

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request) => str_contains(json_encode($request['questions']), 'ds18b20'));
    }

    private function purchaseLine(string $title, array $attrs = []): PurchaseItem
    {
        static $n = 0;
        $order = PurchaseOrder::create([
            'source' => 'aliexpress', 'number' => 'P'.++$n, 'status' => 'delivered', 'ordered_at' => '2026-10-20',
        ]);

        return PurchaseItem::create($attrs + [
            'purchase_order_id' => $order->id,
            'line_key' => PurchaseItem::lineKey($title, null),
            'title_truncated' => $title,
            'quantity' => 2,
            'pack_quantity' => 10,
        ]);
    }

    public function test_analysis_suggests_matching_purchase_item(): void
    {
        $line = $this->purchaseLine('Sensor DS18B20 waterproof', ['variant' => 'Cabo 1m']);

        Http::fake([
            'ollama.test/v1/systemone' => Http::response(['answers' => [
                'compra' => ['type' => 'choice', 'choice' => "p{$line->id}", 'probabilities' => ["p{$line->id}" => 0.8, 'nenhum' => 0.1]],
            ]]),
        ]);

        $this->postJson(route('items.analyze'), ['name' => 'Sensor DS18B20'])
            ->assertOk()
            ->assertJsonPath('data.purchase.id', $line->id)
            ->assertJsonPath('data.purchase.name', 'Sensor DS18B20 waterproof')
            ->assertJsonPath('data.purchase.order_number', $line->order->number)
            ->assertJsonPath('data.purchase.suggested_quantity', 20);

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/v1/systemone')
            && str_contains($request['questions']['compra']['criteria']["p{$line->id}"], 'Cabo 1m'));
    }

    public function test_no_purchase_suggestion_below_threshold_or_without_candidates(): void
    {
        $line = $this->purchaseLine('Sensor DS18B20');
        $this->purchaseLine('Ja ligada', ['item_id' => Item::create(['name' => 'X', 'quantity' => 1])->id]);

        Http::fake([
            'ollama.test/v1/systemone' => Http::response(['answers' => [
                'compra' => ['type' => 'choice', 'choice' => "p{$line->id}", 'probabilities' => ["p{$line->id}" => 0.4]],
            ]]),
        ]);

        $this->postJson(route('items.analyze'), ['name' => 'Sensor'])
            ->assertOk()
            ->assertJsonPath('data.purchase', null);

        Http::assertSent(fn (Request $request) => ! str_contains(json_encode($request['questions']), 'Ja ligada'));

        PurchaseItem::query()->delete();
        Http::fake([
            'ollama.test/v1/systemone' => Http::response(['answers' => [
                'categoria' => ['type' => 'choice', 'choice' => 'sensor', 'probabilities' => ['sensor' => 0.9]],
            ]]),
        ]);

        $this->postJson(route('items.analyze'), ['name' => 'Sensor'])
            ->assertOk()
            ->assertJsonPath('data.purchase', null);

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/v1/systemone')
            && ! isset($request['questions']['compra']));
    }
}
