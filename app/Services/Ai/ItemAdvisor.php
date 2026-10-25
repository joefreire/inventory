<?php

namespace App\Services\Ai;

use App\Models\Item;
use App\Models\Location;
use App\Models\Tag;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Sugere o cadastro de uma peca a partir da foto e/ou do que ja foi digitado.
 *
 * 1. Com foto: o modelo de visao escreve nome, descricao e specs.
 * 2. Uma chamada ao modelo de decisao responde, com probabilidade:
 *    - categoria (lista fixa de config/ai.php);
 *    - quais tags que ja existem se aplicam (evita "esp32" e "ESP-32");
 *    - em qual caixa guardar, entre as que existem, pelo que ja tem nelas;
 *    - se a peca ja esta no inventario ("ja tenho isso?").
 */
class ItemAdvisor
{
    private const MAX_TAGS = 50;
    private const MAX_CANDIDATOS = 200;
    private const ITENS_POR_LOCAL = 20;
    private const MAX_TAGS_NOVAS = 3;

    private const VISION_SYSTEM = <<<'PROMPT'
Voce identifica componentes eletronicos, pecas e ferramentas para um inventario pessoal.
Responda APENAS um objeto JSON:
{
  "name": "nome tecnico curto (ex.: Capacitor eletrolitico 100uF 25V, Modulo rele 2 canais 5V)",
  "description": "o que e e para que serve, em portugues, ate 200 caracteres, para quem esta aprendendo eletronica",
  "specs": {"chave": "valor"},
  "visible_text": "texto ou codigos impressos que da para ler na peca, ou vazio",
  "tags": ["ate 5 tags curtas em minusculas"]
}
Em specs, use so o que da para ver ou deduzir com seguranca (valor, tensao, encapsulamento, marca, modelo, pinos).
Se nao der para identificar, use name "Peca nao identificada" e explique o que aparece na foto.
PROMPT;

    public function __construct(private readonly OllamaClient $ollama)
    {
    }

    /**
     * @param string|null $imagePath Foto da peca (opcional)
     * @param array{name?: ?string, description?: ?string} $typed O que ja foi digitado no formulario
     * @return array<string, mixed>
     */
    public function analyze(?string $imagePath, array $typed = []): array
    {
        $images = $imagePath ? [base64_encode((string) file_get_contents($imagePath))] : [];

        $peca = [
            'name' => trim((string) ($typed['name'] ?? '')),
            'description' => trim((string) ($typed['description'] ?? '')),
            'specs' => [],
            'visible_text' => '',
            'tags' => [],
        ];
        $visionModel = null;

        if ($images) {
            $vision = $this->ollama->chatJson(
                config('ai.models.vision'),
                self::VISION_SYSTEM,
                'Identifique a peca da foto.' . ($peca['name'] ? " O dono acha que e: {$peca['name']}." : ''),
                $images
            );
            $visionModel = $vision['model'];
            $peca = $this->mergeVision($peca, $vision['data']);
        }

        if ($peca['name'] === '' && $peca['description'] === '') {
            throw new AiException('Mande uma foto ou digite o nome da peca.');
        }

        $tags = Tag::query()->withCount('items')->orderByDesc('items_count')->limit(self::MAX_TAGS)->get();
        $locations = Location::query()->orderBy('path')->limit(config('ai.decision_limits.choice_options') - 1)->get();
        $candidatos = $this->candidatos($peca['name']);

        $questions = ['categoria' => $this->categoryQuestion()];
        foreach ($tags as $tag) {
            $questions["tag_{$tag->id}"] = [
                'type' => 'noul',
                'instructions' => "A tag \"{$tag->name}\" descreve esta peca?",
            ];
        }
        if ($locations->isNotEmpty()) {
            $questions['local'] = $this->locationQuestion($locations);
        }
        if ($candidatos->isNotEmpty()) {
            $questions['duplicado'] = $this->duplicateQuestion($candidatos);
        }

        $state = ['peca' => array_filter([
            'nome' => $peca['name'],
            'descricao' => $peca['description'],
            'specs' => $peca['specs'],
            'texto_visivel' => $peca['visible_text'],
        ])];

        $answers = $this->ollama->decide($state, $questions, $images);

        return [
            'name' => $peca['name'],
            'description' => $peca['description'],
            'specs' => $peca['specs'],
            'visible_text' => $peca['visible_text'],
            'category' => $this->categoryAnswer($answers['categoria'] ?? null),
            'tags' => $this->tagsAnswer($answers, $tags, $peca['tags']),
            'location' => $this->locationAnswer($answers['local'] ?? null, $locations),
            'duplicate' => $this->duplicateAnswer($answers['duplicado'] ?? null, $candidatos),
            'models' => ['vision' => $visionModel, 'decision' => config('ai.models.decision')],
        ];
    }

    /**
     * @param array<string, mixed> $peca
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function mergeVision(array $peca, array $data): array
    {
        $specs = is_array($data['specs'] ?? null) ? $data['specs'] : [];
        $specs = array_filter(
            array_map(fn ($v) => is_scalar($v) ? trim((string) $v) : null, $specs),
            fn ($v) => $v !== null && $v !== ''
        );

        return [
            'name' => $peca['name'] ?: Str::limit(trim((string) ($data['name'] ?? '')), 120, ''),
            'description' => $peca['description'] ?: Str::limit(trim((string) ($data['description'] ?? '')), 500, ''),
            'specs' => $specs,
            'visible_text' => Str::limit(trim((string) ($data['visible_text'] ?? '')), 200, ''),
            'tags' => array_values(array_filter(array_map(
                fn ($t) => is_string($t) ? Str::lower(trim($t)) : '',
                (array) ($data['tags'] ?? [])
            ))),
        ];
    }

    /**
     * Itens que podem ser a mesma peca: todos, se forem poucos; senao os que
     * dividem alguma palavra do nome.
     *
     * @return Collection<int, Item>
     */
    private function candidatos(string $nome): Collection
    {
        $query = Item::query()->with('location')->latest();

        if (Item::count() <= self::MAX_CANDIDATOS) {
            return $query->get();
        }

        $palavras = collect(preg_split('/[^\pL\pN]+/u', Str::lower($nome)))
            ->filter(fn ($p) => mb_strlen($p) >= 3)
            ->unique()
            ->take(8);

        if ($palavras->isNotEmpty()) {
            $query->where(function ($q) use ($palavras) {
                foreach ($palavras as $p) {
                    $q->orWhere('name', 'like', "%{$p}%")->orWhere('description', 'like', "%{$p}%");
                }
            });
        }

        return $query->limit(self::MAX_CANDIDATOS)->get();
    }

    /** @return array<string, mixed> */
    private function categoryQuestion(): array
    {
        return [
            'type' => 'choice',
            'instructions' => 'Qual categoria descreve melhor esta peca?',
            'criteria' => config('ai.categories'),
        ];
    }

    /**
     * @param Collection<int, Location> $locations
     * @return array<string, mixed>
     */
    private function locationQuestion(Collection $locations): array
    {
        $locations->load(['items' => fn ($q) => $q->select('id', 'location_id', 'name')]);

        $criteria = [];
        foreach ($locations as $location) {
            $conteudo = $location->items->take(self::ITENS_POR_LOCAL)->pluck('name')->implode('; ');
            $criteria["l{$location->id}"] = Str::limit(trim(
                $location->path
                . ($location->description ? " ({$location->description})" : '')
                . ($conteudo ? ". Tem: {$conteudo}" : '. Vazio')
            ), 600);
        }
        $criteria['nenhum'] = 'Nenhum destes lugares combina com a peca';

        return [
            'type' => 'choice',
            'instructions' => 'Em qual lugar esta peca deve ser guardada, pelo que ja esta guardado em cada um?',
            'criteria' => $criteria,
        ];
    }

    /**
     * @param Collection<int, Item> $candidatos
     * @return array<string, mixed>
     */
    private function duplicateQuestion(Collection $candidatos): array
    {
        $criteria = [];
        foreach ($candidatos->take(config('ai.decision_limits.choice_options') - 1) as $item) {
            $criteria["i{$item->id}"] = Str::limit(
                $item->name . ($item->description ? " - {$item->description}" : ''),
                300
            );
        }
        $criteria['novo'] = 'Nenhum: a peca ainda nao esta no inventario';

        return [
            'type' => 'choice',
            'instructions' => 'Algum destes itens ja cadastrados e a mesma peca (mesmo modelo e valor)?',
            'criteria' => $criteria,
        ];
    }

    /**
     * @param array<string, mixed>|null $answer
     * @return array<string, mixed>|null
     */
    private function categoryAnswer(?array $answer): ?array
    {
        if (!$answer || !isset($answer['choice'])) {
            return null;
        }

        return [
            'value' => $answer['choice'],
            'label' => config("ai.categories.{$answer['choice']}"),
            'probability' => round((float) ($answer['probabilities'][$answer['choice']] ?? 0), 3),
        ];
    }

    /**
     * Tags existentes que o modelo de decisao aprovou, mais as novas que o
     * modelo de visao sugeriu e que nao existem com outro nome.
     *
     * @param array<string, mixed> $answers
     * @param Collection<int, Tag> $tags
     * @param array<int, string> $sugeridas
     * @return array<int, array<string, mixed>>
     */
    private function tagsAnswer(array $answers, Collection $tags, array $sugeridas): array
    {
        $limite = config('ai.thresholds.tag');
        $resultado = [];

        foreach ($tags as $tag) {
            $p = (float) ($answers["tag_{$tag->id}"]['noul'] ?? 0);
            if ($p >= $limite) {
                $resultado[] = ['name' => $tag->name, 'existing' => true, 'probability' => round($p, 3)];
            }
        }

        // Novas so se nao forem variacao de uma tag que ja existe ("3d print" x "3d").
        $existentes = $tags->map(fn ($t) => Str::slug($t->name, ''))->filter()->all();
        $novas = 0;
        foreach (array_unique($sugeridas) as $nome) {
            $slug = Str::slug($nome, '');
            $parecida = collect($existentes)->contains(
                fn ($e) => $slug === '' || str_contains($slug, $e) || str_contains($e, $slug)
            );
            if (!$parecida && $novas < self::MAX_TAGS_NOVAS) {
                $resultado[] = ['name' => $nome, 'existing' => false, 'probability' => null];
                $existentes[] = $slug;
                $novas++;
            }
        }

        return $resultado;
    }

    /**
     * @param array<string, mixed>|null $answer
     * @param Collection<int, Location> $locations
     * @return array<string, mixed>|null
     */
    private function locationAnswer(?array $answer, Collection $locations): ?array
    {
        if (!$answer || empty($answer['probabilities'])) {
            return null;
        }

        $porId = $locations->keyBy('id');
        $opcoes = collect($answer['probabilities'])
            ->filter(fn ($p, $k) => str_starts_with($k, 'l') && $porId->has((int) substr($k, 1)))
            ->sortDesc()
            ->take(3)
            ->map(fn ($p, $k) => [
                'id' => (int) substr($k, 1),
                'path' => $porId[(int) substr($k, 1)]->path,
                'probability' => round((float) $p, 3),
            ])
            ->values();

        return [
            'suggested' => ($opcoes[0]['probability'] ?? 0) >= config('ai.thresholds.location') ? $opcoes[0] : null,
            'options' => $opcoes->all(),
            'none_probability' => round((float) ($answer['probabilities']['nenhum'] ?? 0), 3),
        ];
    }

    /**
     * @param array<string, mixed>|null $answer
     * @param Collection<int, Item> $candidatos
     * @return array<string, mixed>|null
     */
    private function duplicateAnswer(?array $answer, Collection $candidatos): ?array
    {
        $escolha = $answer['choice'] ?? 'novo';
        if ($escolha === 'novo' || !str_starts_with($escolha, 'i')) {
            return null;
        }

        $p = (float) ($answer['probabilities'][$escolha] ?? 0);
        $item = $candidatos->firstWhere('id', (int) substr($escolha, 1));
        if (!$item || $p < config('ai.thresholds.duplicate')) {
            return null;
        }

        return [
            'id' => $item->id,
            'name' => $item->name,
            'quantity' => $item->quantity,
            'location' => $item->location?->path,
            'url' => route('items.show', $item),
            'probability' => round($p, 3),
        ];
    }
}
