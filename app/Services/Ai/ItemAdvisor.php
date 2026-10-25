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
    /** Fotos que vao para a IA: limite de imagens do modelo de decisao na nuvem. */
    public const MAX_FOTOS_IA = 4;

    private const MAX_TAGS = 50;
    private const MAX_CANDIDATOS = 200;
    private const ITENS_POR_LOCAL = 20;
    private const MAX_TAGS_NOVAS = 3;

    private const VISION_SYSTEM = <<<'PROMPT'
Você identifica componentes eletrônicos, peças e ferramentas para um inventário pessoal.
Pode vir uma ou várias fotos da mesma peça: solta, frente, verso, etiqueta ou embalagem/anúncio.
Responda APENAS um objeto JSON:
{
  "name": "nome curto em português, até 45 caracteres, com o modelo quando houver",
  "description": "o que é e para que serve, em português, até 200 caracteres, para quem está aprendendo eletrônica",
  "quantity": número de unidades na foto ou escrito na embalagem (ex.: "10PCS" = 10, "100-Pack" = 100, "5 pares" = 10) ou null se não der para saber,
  "kit": true se for um sortimento de valores diferentes (kit de resistores, caixa de fusíveis variados), senão false,
  "specs": {"chave": "valor"},
  "visible_text": "texto ou códigos impressos que dá para ler, sem números de pedido nem 'Made in China'",
  "tags": ["até 4 tags"]
}
Nome: em português, sem tradução entre parênteses, sem a palavra "Kit" se não for sortimento.
Exemplos de nome: "Relé 5V 10A JQC-3F", "Servo MG996R", "Módulo ponte H L298N", "Rolamento 608ZZ", "Buzzer passivo 12mm".
Em specs use só o que dá para ver ou deduzir com segurança e estas chaves, em minúsculas e sem acento:
modelo, marca, valor, tensao, corrente, potencia, tolerancia, encapsulamento, pinos, canais, dimensoes, material, faixa, interface, quantidade_por_pacote.
Tags: minúsculas, sem acento, específicas da peça (ex.: "ponte-h", "lipo", "esp8266", "12v", "pwm").
Nunca use tags genéricas que valem para qualquer peça: componente, eletrica, eletronica, hardware, peca, modulo.
Se não der para identificar, use name "Peça não identificada" e explique o que aparece na foto.
PROMPT;

    public function __construct(private readonly OllamaClient $ollama)
    {
    }

    /**
     * @param array<int, string> $imagePaths Fotos da mesma peca (frente, verso, etiqueta), ate 4
     * @param array{name?: ?string, description?: ?string} $typed O que ja foi digitado no formulario
     * @return array<string, mixed>
     */
    public function analyze(array $imagePaths, array $typed = []): array
    {
        $images = array_map(
            fn ($path) => base64_encode((string) file_get_contents($path)),
            array_slice(array_values(array_filter($imagePaths)), 0, self::MAX_FOTOS_IA)
        );

        $peca = [
            'name' => trim((string) ($typed['name'] ?? '')),
            'description' => trim((string) ($typed['description'] ?? '')),
            'specs' => [],
            'visible_text' => '',
            'tags' => [],
            'quantity' => null,
            'kit' => false,
        ];
        $visionModel = null;

        if ($images) {
            $vision = $this->ollama->chatJson(
                config('ai.models.vision'),
                self::VISION_SYSTEM,
                (count($images) > 1
                    ? 'Estas ' . count($images) . ' fotos são da MESMA peça (frente, verso, etiqueta ou embalagem). Junte o que cada uma mostra.'
                    : 'Identifique a peça da foto.')
                . ($peca['name'] ? " O dono acha que é: {$peca['name']}." : ''),
                $images
            );
            $visionModel = $vision['model'];
            $peca = $this->mergeVision($peca, $vision['data']);
        }

        if ($peca['name'] === '' && $peca['description'] === '') {
            throw new AiException('Mande uma foto ou digite o nome da peça.');
        }

        // Tags genericas ("componente", "eletrica") ficam fora: o modelo de decisao
        // aprova elas para tudo e elas nao ajudam a filtrar.
        $tags = Tag::query()->withCount('items')->orderByDesc('items_count')->get()
            ->reject(fn (Tag $t) => self::tagGenerica($t->name))
            ->take(self::MAX_TAGS)
            ->values();
        $locations = Location::query()->orderBy('path')->limit(config('ai.decision_limits.choice_options') - 1)->get();
        $candidatos = $this->candidatos($peca['name']);

        $questions = ['categoria' => $this->categoryQuestion()];
        foreach ($tags as $tag) {
            $questions["tag_{$tag->id}"] = [
                'type' => 'noul',
                'instructions' => "A tag \"{$tag->name}\" descreve esta peça especificamente (não vale para qualquer peça)?",
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
            'quantity' => $peca['quantity'],
            'kit' => $peca['kit'],
            'category' => $this->categoryAnswer($answers['categoria'] ?? null),
            'tags' => $this->tagsAnswer($answers, $tags, $peca['tags']),
            'location' => $this->locationAnswer($answers['local'] ?? null, $locations),
            'duplicate' => $this->duplicateAnswer($answers['duplicado'] ?? null, $candidatos),
            'models' => ['vision' => $visionModel, 'decision' => config('ai.models.decision')],
            'photos_used' => count($images),
        ];
    }

    /**
     * @param array<string, mixed> $peca
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function mergeVision(array $peca, array $data): array
    {
        $specs = [];
        foreach ((is_array($data['specs'] ?? null) ? $data['specs'] : []) as $chave => $valor) {
            $valor = is_scalar($valor) ? trim((string) $valor) : '';
            $chave = Str::slug(Str::ascii((string) $chave), '_');
            if ($valor !== '' && $chave !== '') {
                $specs[$chave] = $valor;
            }
        }

        $quantidade = filter_var($data['quantity'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]);

        return [
            'name' => $peca['name'] ?: Str::limit(trim((string) ($data['name'] ?? '')), 80, ''),
            'description' => $peca['description'] ?: Str::limit(trim((string) ($data['description'] ?? '')), 500, ''),
            'specs' => $specs,
            'visible_text' => Str::limit(trim((string) ($data['visible_text'] ?? '')), 200, ''),
            'tags' => array_values(array_filter(
                array_map(fn ($t) => is_string($t) ? Str::lower(trim($t)) : '', (array) ($data['tags'] ?? [])),
                fn ($t) => $t !== '' && !self::tagGenerica($t)
            )),
            'quantity' => $quantidade === false ? null : $quantidade,
            'kit' => (bool) ($data['kit'] ?? false),
        ];
    }

    /**
     * Tag que vale para quase qualquer peca (config ai.generic_tags).
     */
    public static function tagGenerica(string $nome): bool
    {
        return in_array(Str::slug(Str::ascii($nome), ''), config('ai.generic_tags', []), true);
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
        $maximo = config('ai.max_tags', 5);
        $resultado = [];

        $aprovadas = [];
        foreach ($tags as $tag) {
            $p = (float) ($answers["tag_{$tag->id}"]['noul'] ?? 0);
            if ($p >= $limite) {
                $aprovadas[] = ['name' => $tag->name, 'existing' => true, 'probability' => round($p, 3)];
            }
        }
        usort($aprovadas, fn ($a, $b) => $b['probability'] <=> $a['probability']);
        $resultado = array_slice($aprovadas, 0, $maximo);

        // Novas so se nao forem variacao de uma tag que ja existe ("3d print" x "3d",
        // "eletrica" x "elétrica"). Partes curtas (<4) so contam se forem iguais.
        $existentes = $tags->map(fn ($t) => Str::slug(Str::ascii($t->name), ''))->filter()->all();
        $novas = 0;
        foreach (array_unique($sugeridas) as $nome) {
            if (count($resultado) >= $maximo) {
                break;
            }
            $slug = Str::slug(Str::ascii($nome), '');
            $parecida = $slug === '' || collect($existentes)->contains(fn ($e) => $e === $slug
                || (min(strlen($e), strlen($slug)) >= 4 && (str_contains($slug, $e) || str_contains($e, $slug))));
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
