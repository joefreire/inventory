# Arquitetura

Inventário pessoal de peças eletrônicas, usado quase sempre no celular.
Laravel 12 (PHP 8.4), SQLite, Blade + JS em módulos (Vite), IA no Ollama Cloud.

## Camadas

```
HTTP        routes/web.php ─► Controllers (finos) ─► FormRequests (validação, autorização)
                                     │
Domínio     Services (escrita: ItemService)   Queries (leitura: ItemFilter)
            Locations (NfcCode, LocationTree)  Tags (TagNormalizer)  Media (URL privada)
                                     │
IA          App\Ai\ItemAdvisor ─► PartIdentifier ─► Contracts\VisionModel ──► Ollama\OllamaVisionModel
                     │                                                         (cadeia de modelos)
                     └─► Decisions\* (PartDecision) ─► Contracts\DecisionModel ─► Ollama\OllamaDecisionModel
            App\Ai\Search\NaturalSearch ─────────────► Contracts\DecisionModel
                                     │
Dados       Models (Item, Location, Tag) + spatie/laravel-medialibrary (fotos)
```

Regras do projeto:

- **Controller não tem regra.** Lê do FormRequest, chama um serviço, devolve view/JSON.
- **Validação e autorização no FormRequest.** `authorize()` exige usuário logado.
- **Escrita em Services, leitura em Queries.** `ItemService` faz transação; quantidade é
  um `UPDATE` atômico (dois toques rápidos no + não se perdem).
- **Uma regra, um lugar.** Formato do código NFC: `App\Locations\NfcCode` (o JS só manda
  o texto lido). Tags genéricas e variações: `App\Tags\TagNormalizer`. Limites e
  categorias: `config/ai.php`.
- **Código em inglês, interface em português.**

## IA (app/Ai)

O resto do app depende só dos **contratos** `VisionModel` e `DecisionModel`.
Trocar de provedor = outra classe que implementa o contrato + o bind em
`App\Providers\AiServiceProvider`.

| Peça | Papel |
|---|---|
| `Identification\PartIdentifier` | fotos (+ o que foi digitado) → `PartDescription` tipada. Prompt em `resources/prompts/identify-part.md` |
| `Decisions\PartDecision` | contrato de uma pergunta fechada ao modelo de decisão |
| `Decisions\CategoryDecision`, `TagsDecision`, `LocationDecision`, `DuplicateDecision` | uma classe por pergunta |
| `ItemAdvisor` | orquestra: identifica, junta as perguntas de todas as decisões numa chamada só, interpreta |
| `Search\NaturalSearch` | "tenho algo pra…": um sim/não por item, com limite de itens e cache |

**Pergunta nova** (ex.: "precisa de cuidado com ESD?"): classe que implementa
`PartDecision`, listada em `config/ai.php` → `decisions`. Vai na mesma chamada,
sem custo de ida e volta extra, e aparece na resposta da análise com a chave `key()`.

## Segurança

| Ameaça | Proteção |
|---|---|
| Acesso sem login | todas as rotas atrás de `auth`; sem cadastro aberto; usuário por `php artisan inventario:usuario` |
| Força bruta no login | 5 erros por e-mail+IP bloqueiam 1 min (`LoginRequest`) + 10/min por IP (`throttle:login`) |
| Fotos vazando | disco privado `media`; URLs passam por `MediaController` (com login). Antes: `/storage/{id}` público |
| XSS | Blade escapa; JS monta HTML com `esc()`; **CSP** sem script inline nem `eval`, só `'self'` (nada de CDN) |
| Clickjacking, sniffing | `X-Frame-Options: DENY`, `frame-ancestors 'none'`, `nosniff`, HSTS em HTTPS (`SecurityHeaders`) |
| Upload malicioso | só jpg/png/webp/heic de verdade (conteúdo, não extensão), 12 MB, 8000 px; sem SVG |
| Custo/abuso da IA | `throttle:ai` (20/min por usuário, `AI_RATE_LIMIT`) |
| Sessão | cookie seguro, `SameSite=lax`, regenerada no login |
| Dependências | `composer audit` e `npm audit` zerados (rodar `composer audit-all`) |

## Escala

- Lista de caixas: **1 consulta** (`LocationTree`), somas em memória. Antes: 1 por caixa.
- Lista de peças: paginada (24), relações carregadas antes (`with`).
- Busca em linguagem natural: avalia no máximo `AI_SEARCH_MAX_ITEMS` (500) itens, em lotes
  paralelos de 64, com cache de 10 min por pergunta+versão do inventário.
- "Já tenho isso?": todos os itens enquanto ≤ 254 (limite de opções do modelo); depois,
  só os que dividem palavra do nome.
- SQLite basta para um usuário; trocar por MySQL/Postgres é só `DB_CONNECTION`
  (a quantidade atômica usa `CASE`, portável).

## Front (resources/js)

- `app.js`: Bootstrap, ícones e fontes (servidos pelo app), comportamento comum
  (avisos, formulário de um toque só, `data-confirm`, aba "Ler tag") e um módulo por
  página, carregado só onde é usado (`<body data-page="...">`).
- `lib/`: `api` (fetch com CSRF), `toast`, `image` (reduz a foto no celular), `nfc`, `dom`.
- `components/`: `PhotoPicker`, `TagInput`.
- `pages/`: `item-create`, `item-form`, `item-show`, `item-ask`, `location-show`, `tag-form`.
- URLs vêm das rotas por atributos `data-*`; o JS não monta caminho.

## Qualidade

```bash
composer check       # Pint + Larastan nível 6 + testes
composer audit-all   # vulnerabilidades PHP e JS
npm run build        # front (public/build vai no deploy)
```

Os testes simulam a IA (`Http::fake`), o disco de fotos (`Storage::fake('media')`) e
cobrem regras de segurança (login, cabeçalhos, fotos privadas).
