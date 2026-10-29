# Compras do AliExpress — plano de implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Área "Compras" no inventário, alimentada pelos e-mails do AliExpress via uma skill do Claude Code, com a IA completando nome, specs e pacote, cadastro a partir da compra e ligação do histórico às peças existentes.

**Architecture:** A skill `/importar-pedidos` (homelab) lê o Gmail pelo MCP, um parser Python transforma cada e-mail em JSON sem dados pessoais, e o JSON entra no app pelo comando `compras:importar` (via SSH) ou pela tela de upload; os dois usam o mesmo `PurchaseImporter`. Cada item novo vira um job `EnrichPurchaseItem` (fila `database`, worker novo no canivete), que guarda a foto oficial no disco privado e pergunta ao `VisionModel`. A ligação com peças usa o `DecisionModel`: uma `PartDecision` nova no cadastro e um job de propostas em lote.

**Tech Stack:** Laravel 12, PHP 8.4, SQLite, spatie/laravel-medialibrary, Blade + JS em módulos (Vite), Ollama Cloud (contratos `VisionModel`/`DecisionModel`), Playwright; Python 3 + pytest para o parser da skill.

**Spec:** `docs/superpowers/specs/2026-10-29-compras-aliexpress-design.md`

## Global Constraints

- Só AliExpress agora; `source` é string livre (`aliexpress`, depois `mercadolivre`).
- O app **nunca** acessa Gmail, Chrome ou site das lojas. A única rede de saída nova é baixar a foto oficial, e só de hosts `*.aliexpress-media.com` e `*.alicdn.com`.
- Endereço, telefone e nome de destinatário não entram no JSON, no banco nem nos logs.
- Produtos do bloco "Você também vai gostar" não viram itens.
- Reimportar o mesmo pedido atualiza, sem duplicar. Identidade do pedido: `(source, number)`; do item: `(purchase_order_id, line_key)`, com `line_key = sha1(title_truncated."|".variant)`.
- Status só anda para frente: `awaiting` → `confirmed` → `shipped` → `delivered`. `cancelled` e `refunded` são finais e nunca pendentes.
- Links guardados sem `tracelog`, `rowan_*`, `mem_info`, `edm_*`, `ck` (o importer limpa, mesmo que a skill já tenha limpado).
- IA falhou → item fica `enrichment_error` preenchido e `enriched_at` nulo; nunca aparece como enriquecido.
- Ligação do histórico em lote **não** muda `items.quantity`. "Juntar a uma peça" soma `quantity × pack_quantity` só depois de confirmar.
- Quantidade sugerida = `quantity × (pack_quantity ?? 1)`.
- Nome completado pela IA é exibido com o selo "completado pela IA"; `title_truncated` original fica guardado.
- Regras de `docs/ARCHITECTURE.md`: controller fino, validação no FormRequest, escrita em Services, código em inglês e tela em português, URLs do JS por `data-*`, CSP sem script inline.
- Verificação de toda task: `php8.4 /usr/local/bin/composer check` verde.

## Review Focus

1. JSON com campos extras (`address`, `phone`, `buyer`) ou com produto de propaganda: o importer ignora o que não está na lista branca; nada disso é gravado. → teste na Task 2.
2. `image_url` apontando para outro host (`http://127.0.0.1/...`, `https://evil.test/x.jpg`): o job recusa sem fazer requisição e marca o erro. → teste na Task 3.
3. Reimportar um pedido `delivered` com status `shipped` (e-mails fora de ordem): continua `delivered`. → teste na Task 2.
4. Peça apagada depois de ligada: o item de compra volta a ficar sem peça (FK `nullOnDelete`) e reaparece nas pendências. → teste na Task 5.
5. Upload de arquivo que não é JSON, ou JSON sem `orders`: erro de validação na tela, nada gravado. → teste na Task 4.

---

### Task 1: Tabelas e models de compra

**Files:**
- Create: `database/migrations/2026_10_29_000001_create_purchase_orders_table.php`, `database/migrations/2026_10_29_000002_create_purchase_items_table.php`
- Create: `app/Purchases/PurchaseStatus.php`, `app/Models/PurchaseOrder.php`, `app/Models/PurchaseItem.php`
- Modify: `app/Models/Item.php` (relação `purchaseItems()`)
- Test: `tests/Unit/Models/PurchaseItemTest.php`

**Interfaces:**
- Produces:
  - `purchase_orders`: `id, source string, number string, ordered_at date null, status string, total_cents int null, currency string(3) default 'BRL', url string null, timestamps`, unique `(source, number)`.
  - `purchase_items`: `id, purchase_order_id FK cascade, line_key string(40), title_truncated string, variant string null, quantity int default 1, image_url string null, name string null, description text null, specs json null, pack_quantity int null, is_part bool null, enriched_at timestamp null, enrichment_error string null, item_id FK items null nullOnDelete, dismissed_at timestamp null, proposed_item_id FK items null nullOnDelete, proposal_probability float null, proposal_rejected_at timestamp null, timestamps`, unique `(purchase_order_id, line_key)`.
  - `enum PurchaseStatus: string { Awaiting='awaiting'; Confirmed='confirmed'; Shipped='shipped'; Delivered='delivered'; Cancelled='cancelled'; Refunded='refunded'; public function isFinal(): bool; public function advanceTo(self $next): self; public function label(): string }` — `advanceTo` devolve `$next` só se avança na ordem ou se `$next` é final; status final nunca muda. Rótulos: Aguardando confirmação, Confirmado, Enviado, Entregue, Cancelado, Reembolsado.
  - `PurchaseOrder` (cast `status` → `PurchaseStatus`, `ordered_at` → date; `items(): HasMany`).
  - `PurchaseItem implements HasMedia` (coleção `photo`, single file, disco `media`, conversões `thumb`/`preview` como em `Item`); casts `specs` array, `is_part` bool, datas; `order(): BelongsTo`, `item(): BelongsTo`, `proposedItem(): BelongsTo`; `displayName(): string` (`name ?? title_truncated`); `suggestedQuantity(): int` (`quantity * (pack_quantity ?? 1)`); `static lineKey(string $title, ?string $variant): string`; scope `pending(Builder)` = pedido `delivered`, `item_id` nulo, `dismissed_at` nulo, `is_part` não falso (nulo conta: ainda não enriquecido).
  - `Item::purchaseItems(): HasMany`.

- [ ] **Step 1: Testes que falham** — `PurchaseItemTest`:
  - `test_status_only_moves_forward`: `Delivered->advanceTo(Shipped) === Delivered`; `Shipped->advanceTo(Delivered) === Delivered`; `Delivered->advanceTo(Refunded) === Refunded`; `Cancelled->advanceTo(Delivered) === Cancelled`.
  - `test_pending_scope`: com pedidos `delivered` e `shipped`, itens ligado, dispensado, `is_part=false`, `is_part=null` e um válido → `PurchaseItem::pending()` traz só o válido e o `is_part=null` do pedido entregue.
  - `test_suggested_quantity`: `quantity=2, pack_quantity=100` → 200; `pack_quantity=null` → 2.
  - `test_line_key_is_stable`: mesma entrada → mesmo sha1 de 40 chars; variante diferente → chave diferente.
- [ ] **Step 2:** `php8.4 artisan test --filter=PurchaseItemTest` → FAIL (classes não existem).
- [ ] **Step 3:** Migrations, enum e models conforme Interfaces.
- [ ] **Step 4:** `php8.4 artisan test --filter=PurchaseItemTest` → PASS; `composer check` verde.
- [ ] **Step 5:** Commit `feat(compras): tabelas e models de pedidos e itens de compra`.

### Task 2: Importer e comandos de importação

**Files:**
- Create: `app/Purchases/PurchaseImporter.php`, `app/Purchases/ImportResult.php`, `app/Purchases/CleanUrl.php`
- Modify: `routes/console.php` (comandos `compras:importar`, `compras:pedidos`)
- Test: `tests/Feature/PurchaseImportTest.php`, `tests/fixtures/purchases/aliexpress.json`

**Interfaces:**
- Consumes: models e `PurchaseStatus` da Task 1.
- Produces:
  - `PurchaseImporter::import(array $payload): ImportResult` — valida o formato (Validator; `source` obrigatório, `orders.*.number` obrigatório, `items.*.title_truncated` obrigatório, `quantity` inteiro ≥ 1, `total` decimal em string ou número), lista branca de campos, transação, `updateOrCreate` por identidade, status via `advanceTo`, dispara `EnrichPurchaseItem` para itens criados (job da Task 3; até lá, `Bus::fake` nos testes).
  - `final readonly class ImportResult { int $ordersCreated; int $ordersUpdated; int $itemsCreated; list<string> $errors }`.
  - `CleanUrl::strip(?string $url): ?string` — remove os parâmetros de rastreamento listados nas Global Constraints, mantém `orderId`.
  - `php artisan compras:importar {file}` — lê o JSON, chama o importer, imprime `Pedidos novos: X, atualizados: Y, itens novos: Z` e erros; exit 1 se o JSON for inválido.
  - `php artisan compras:pedidos {source}` — imprime JSON `[{"number": "...", "status": "..."}]` dos pedidos já importados (a skill usa para ler só o que falta).
  - Formato do payload: o do spec (`source`, `orders[].number/ordered_at/status/total/currency/url/items[].title_truncated/variant/quantity/image_url`).

- [ ] **Step 1: Testes que falham** — `PurchaseImportTest` (fixture com 2 pedidos, 3 itens, e um pedido com chaves `address`, `phone`, `buyer_name` e um item extra `recommended`):
  - `test_import_creates_orders_and_items`: 2 pedidos, 3 itens; `total_cents` = 5679 para `"56.79"`.
  - `test_reimport_updates_without_duplicating`: importar 2× → 2 pedidos, 3 itens; `ordersUpdated === 2` na segunda.
  - `test_status_never_goes_back`: pedido `delivered`, reimporta com `shipped` → continua `delivered`.
  - `test_personal_data_and_unknown_fields_are_ignored`: nenhuma coluna/valor contém o telefone ou o endereço da fixture (`assertDatabaseMissing` em todas as colunas texto relevantes e `json_encode` dos models sem a string); o campo `recommended` não vira item.
  - `test_tracking_params_are_stripped`: `url` com `&tracelog=...&rowan_id1=...&mem_info=...` vira `https://www.aliexpress.com/p/order/detail.html?orderId=8215761587024729`.
  - `test_invalid_payload_saves_nothing`: sem `orders` → `errors` não vazio, 0 linhas.
  - `test_import_command_and_orders_command`: `artisan('compras:importar', ['file' => fixture])->assertExitCode(0)`; `artisan('compras:pedidos', ['source' => 'aliexpress'])` imprime JSON com os 2 números.
  - `test_new_items_are_queued_for_enrichment`: `Bus::fake()`; 3 jobs na primeira importação, 0 na segunda.
- [ ] **Step 2:** rodar → FAIL.
- [ ] **Step 3:** Implementar conforme Interfaces.
- [ ] **Step 4:** rodar → PASS; `composer check`.
- [ ] **Step 5:** Commit `feat(compras): importer de pedidos e comandos compras:importar e compras:pedidos`.

### Task 3: Enriquecimento pela IA e worker da fila

**Files:**
- Create: `app/Purchases/PurchaseItemEnricher.php`, `app/Jobs/EnrichPurchaseItem.php`, `resources/prompts/complete-purchase-item.md`
- Modify: `app/Providers/AiServiceProvider.php` (bind do enricher), `routes/console.php` (`compras:enriquecer`), `config/ai.php` (`purchase_image_hosts`)
- Create: `deploy/inventario-queue.service`; Modify: `scripts/deploy.sh` (`systemctl restart inventario-queue` no fim), `README.md` (seção deploy: instalar o serviço)
- Test: `tests/Feature/PurchaseEnrichmentTest.php`

**Interfaces:**
- Consumes: `VisionModel::describe(string $system, string $prompt, array $images): array{model, data}`; `PurchaseItem` da Task 1.
- Produces:
  - `PurchaseItemEnricher::enrich(PurchaseItem $item, string $imageBase64): array{name: string, description: string, specs: array<string,string>, pack_quantity: ?int, is_part: bool}` — prompt pede JSON `{name, description, specs, pack_quantity, is_part}`; o prompt diz que o nome cortado é o começo do título real do anúncio e que `pack_quantity` vem da variação ("100 PCS" = 100, "5 pares" = 10). `is_part` = peça eletrônica, componente, ferramenta ou material de bancada. Specs normalizadas como em `PartIdentifier::specs()` (extrair para um helper compartilhado `App\Ai\Identification\Specs::normalize(mixed $raw): array`).
  - `EnrichPurchaseItem(int $purchaseItemId)` (ShouldQueue, `tries = 3`, `backoff = [60, 300]`): baixa a foto se não houver mídia (só hosts de `config('ai.purchase_image_hosts')` = `['aliexpress-media.com', 'alicdn.com']`, por sufixo de domínio, HTTPS; senão grava `enrichment_error = 'Host de imagem não permitido'` e termina sem requisição); guarda via `addMediaFromString` na coleção `photo`; chama o enricher; sucesso → campos + `enriched_at = now()`, `enrichment_error = null`; `AiException` → `enrichment_error` com a mensagem e relança para retry; no `failed()` mantém o erro.
  - `php artisan compras:enriquecer {--falhas}` — enfileira itens com `enriched_at` nulo (com `--falhas`, só os com erro).
  - `deploy/inventario-queue.service`: `php8.4 artisan queue:work --queue=default --sleep=3 --tries=3 --max-time=3600`, `User=www-data`, `WorkingDirectory=/var/www/inventario`, `Restart=always`.

- [ ] **Step 1: Testes que falham** (`Storage::fake('media')`, `Http::fake`, config de IA como em `AiTest::setUp`):
  - `test_enrich_fills_name_specs_pack_and_photo`: imagem fake do host permitido + resposta de visão `{name: "SONOFF ZBMINIR2 Interruptor Inteligente Zigbee", specs: {modelo: "ZBMINIR2"}, pack_quantity: 1, is_part: true}` → campos gravados, `enriched_at` não nulo, 1 mídia em `photo`.
  - `test_disallowed_image_host_is_refused_without_request`: `image_url = https://evil.test/x.jpg` → `enrichment_error = 'Host de imagem não permitido'`, `Http::assertNothingSent()`.
  - `test_ai_failure_is_recorded_not_silent`: visão 500 em todos os modelos → `enriched_at` nulo, `enrichment_error` não vazio.
  - `test_existing_photo_is_not_downloaded_again`: item já com mídia → nenhuma requisição ao host da imagem.
  - `test_enrich_command_queues_only_missing`: `Bus::fake`; 2 itens sem enriquecimento e 1 enriquecido → 2 jobs; `--falhas` só os com erro.
- [ ] **Step 2:** rodar → FAIL.
- [ ] **Step 3:** Implementar; extrair `Specs::normalize` e usá-lo também em `PartIdentifier` (os testes de `AiTest` seguem verdes).
- [ ] **Step 4:** rodar → PASS; `composer check`.
- [ ] **Step 5:** Commit `feat(compras): IA completa nome, specs e pacote e guarda a foto oficial`.

### Task 4: Telas de Compras e importação por upload

**Files:**
- Create: `app/Http/Controllers/PurchaseController.php`, `app/Http/Requests/ImportPurchasesRequest.php`, `app/Queries/PurchaseItemFilter.php`
- Create: `resources/views/purchases/index.blade.php`, `resources/views/purchases/import.blade.php`, `resources/views/purchases/_item-row.blade.php`
- Modify: `routes/web.php`, `resources/views/items/index.blade.php` (menu ⋮: link "Compras" acima de "Tags"), `app/Http/Controllers/MediaController.php` só se precisar (a mídia de `PurchaseItem` já passa pela rota `media.show`)
- Test: `tests/Feature/PurchaseControllerTest.php`, `e2e/purchases.spec.ts`

**Interfaces:**
- Consumes: `PurchaseImporter::import()` (Task 2), scope `pending` e `displayName()` (Task 1).
- Produces:
  - Rotas (dentro de `auth`): `GET compras` → `purchases.index`; `GET compras/importar` → `purchases.import`; `POST compras/importar` → `purchases.import.store`.
  - `PurchaseItemFilter::fromRequest(Request)`: `?mostrar=pendentes` (padrão quando houver pendentes), `pecas` (padrão senão: `is_part` não falso), `todos`; `?q=` busca em `name`/`title_truncated`; ordena por `ordered_at` desc; pagina 24.
  - `ImportPurchasesRequest`: `file` obrigatório, `mimes:json,txt`, `max:5120`; `payload(): array` (decodifica; JSON inválido → erro de validação em `file`).
  - Linha do item: foto (thumb da mídia ou ícone), nome com selo "completado pela IA" quando `name` não nulo, "Pedido <n> · <data> · <status>", "x<quantity>" e, se `pack_quantity > 1`, "= <suggestedQuantity> un"; estado "IA pendente"/"IA falhou" quando for o caso.
  - Tela de importação: upload, e depois mostra o `ImportResult` como flash.

- [ ] **Step 1: Testes que falham** (`PurchaseControllerTest`):
  - `test_compras_requires_login` (redirect `/login`).
  - `test_index_defaults_to_pending_items`: um pendente e um ligado → só o pendente; `?mostrar=todos` → os dois.
  - `test_index_hides_non_parts_by_default_when_no_pending`.
  - `test_upload_imports_file`: upload da fixture da Task 2 → redirect com flash "2 pedidos novos"; linhas criadas.
  - `test_upload_rejects_non_json_and_missing_orders` → `assertSessionHasErrors('file')`, 0 linhas.
  - E2E `purchases.spec.ts`: importa a fixture pela tela, vê o item na lista "Compras", o menu ⋮ de Peças leva a Compras.
- [ ] **Step 2:** rodar → FAIL.
- [ ] **Step 3:** Implementar.
- [ ] **Step 4:** `composer check` e `npm run e2e` verdes.
- [ ] **Step 5:** Commit `feat(compras): area Compras com pendentes e importacao por upload`.

### Task 5: Cadastrar, juntar e dispensar a partir da compra

**Files:**
- Create: `app/Purchases/PurchaseLinker.php`, `app/Http/Requests/JoinPurchaseItemRequest.php`
- Modify: `app/Http/Controllers/PurchaseController.php`, `app/Http/Controllers/ItemController.php` (`create` aceita `?compra=<purchase_item_id>`; `store` liga), `app/Http/Requests/StoreItemRequest.php` (`purchase_item_id` nullable exists), `resources/views/items/create.blade.php`, `resources/views/items/show.blade.php` (bloco "Compras"), `resources/views/purchases/_item-row.blade.php` (ações), `routes/web.php`
- Test: `tests/Feature/PurchaseLinkTest.php`

**Interfaces:**
- Consumes: `ItemService::create()` e `adjustQuantity()`; `PurchaseItem::suggestedQuantity()`.
- Produces:
  - `PurchaseLinker::linkNew(PurchaseItem $p, Item $item): void` — liga e, se a peça foi salva sem fotos, copia a mídia `photo` da compra para a coleção `photos` da peça (`copy()` da medialibrary).
  - `PurchaseLinker::join(PurchaseItem $p, Item $item, bool $addQuantity): int` — liga e, se `$addQuantity`, soma `suggestedQuantity()` via `ItemService::adjustQuantity`; devolve a nova quantidade.
  - `PurchaseLinker::dismiss(PurchaseItem $p): void` / `undismiss`.
  - Rotas: `POST compras/itens/{purchaseItem}/juntar` (`item_id`, `somar` bool) → `purchases.items.join`; `PATCH compras/itens/{purchaseItem}/dispensar` → `purchases.items.dismiss`.
  - `GET items/create?compra=<id>`: pré-preenche `name` (`displayName()`), `description`, `quantity` (`suggestedQuantity()`), `ai_metadata` com `specs`; mostra a foto oficial com o texto "Foto do anúncio (usada se você não tirar outra)"; campo oculto `purchase_item_id`.
  - Botão "Juntar a uma peça que já tenho": folha inferior com busca de peças; confirmação "Somar <N> unidades a <peça>?" com opção de não somar.
  - Página da peça: grupo "Compras" com cada compra: "AliExpress · <data> · pedido <n> · x<qtd>", link do pedido (`target=_blank`, `rel=noopener`).

- [ ] **Step 1: Testes que falham** (`PurchaseLinkTest`):
  - `test_create_from_purchase_prefills_form`: `GET items/create?compra=ID` mostra nome, quantidade 200 (`x2`, pacote 100) e `purchase_item_id`.
  - `test_store_links_and_copies_official_photo_when_none_uploaded`: depois do POST, `purchase_item.item_id` = nova peça; peça com 1 foto.
  - `test_store_with_own_photo_does_not_copy`: peça com só a foto enviada.
  - `test_join_adds_quantity_only_when_asked`: peça com 5; `somar=1` → 205; outro item com `somar=0` → continua 205; os dois ligados.
  - `test_dismiss_removes_from_pending`.
  - `test_deleting_item_returns_purchase_to_pending` (Review Focus 4).
  - `test_item_page_lists_its_purchases`: duas compras ligadas aparecem com número e link limpo.
- [ ] **Step 2:** rodar → FAIL.
- [ ] **Step 3:** Implementar.
- [ ] **Step 4:** `composer check`, `npm run e2e`.
- [ ] **Step 5:** Commit `feat(compras): cadastrar, juntar e dispensar a partir da compra`.

### Task 6: Sugestão de compra no cadastro de peça solta

**Files:**
- Create: `app/Ai/Decisions/PurchaseDecision.php`
- Modify: `config/ai.php` (`decisions` + `thresholds.purchase = 0.6`), `app/Providers/AiServiceProvider.php`, `resources/js/pages/item-create.js` (cartão de sugestão), `resources/views/items/create.blade.php` (oculto `purchase_item_id` já existe da Task 5)
- Test: `tests/Feature/AiTest.php` (novos testes)

**Interfaces:**
- Consumes: `PartDecision`, `DecisionModel`, scope `pending` (Task 1).
- Produces: `PurchaseDecision implements PartDecision`, `key() = 'purchase'`; candidatos = itens de compra sem peça e não dispensados, `is_part` não falso, mais recentes primeiro, até `maxChoiceOptions() - 1`; uma pergunta `choice` "Esta peça é algum destes itens comprados?" com critério `p<id>` = `displayName()` + variação, e `nenhum`; `interpret()` devolve `{id, name, order_number, ordered_at, photo_url, probability}` ou `null` (abaixo do limiar ou `nenhum`). No JS, o cartão "Parece o item X do pedido Y (data)" com "É este" (preenche `purchase_item_id` e a quantidade sugerida) e "Não é".

- [ ] **Step 1: Testes que falham**:
  - `test_analysis_suggests_matching_purchase_item`: decisão responde `p<id>` com 0.8 → `data.purchase.id` = id.
  - `test_no_purchase_suggestion_below_threshold_or_without_candidates`: 0.4 → `null`; sem itens de compra → nenhuma pergunta `compra` enviada.
- [ ] **Step 2:** rodar → FAIL.
- [ ] **Step 3:** Implementar.
- [ ] **Step 4:** `composer check`, `npm run e2e`.
- [ ] **Step 5:** Commit `feat(compras): cadastro sugere de qual compra veio a peca`.

### Task 7: Ligação do histórico em lote

**Files:**
- Create: `app/Purchases/LinkProposer.php`, `app/Jobs/ProposePurchaseLinks.php`, `app/Http/Requests/ReviewProposalsRequest.php`, `resources/views/purchases/proposals.blade.php`
- Modify: `app/Http/Controllers/PurchaseController.php`, `routes/web.php`, `routes/console.php` (`compras:propor`), `resources/views/purchases/index.blade.php` (botão "Ligar compras antigas às peças" e contador de propostas)
- Test: `tests/Feature/PurchaseProposalTest.php`

**Interfaces:**
- Consumes: `DecisionModel::decide()`, `DuplicateCandidates::for(string $name, int $max)`.
- Produces:
  - `LinkProposer::propose(Collection $purchaseItems): int` — para cada item enriquecido, sem peça, não dispensado e sem proposta recusada, uma pergunta `choice` com as peças candidatas (`DuplicateCandidates`) + `nenhuma`; até 64 perguntas por chamada (o `OllamaDecisionModel` já divide em lotes); grava `proposed_item_id` e `proposal_probability` quando ≥ `config('ai.thresholds.duplicate')`; devolve quantas propostas gravou.
  - `ProposePurchaseLinks` (job) e `php artisan compras:propor`.
  - Rotas: `POST compras/propostas/gerar` → enfileira o job; `GET compras/propostas` → lista com as duas fotos lado a lado (compra × peça), checkbox marcado por padrão; `POST compras/propostas` (`confirmar[]`, `recusar[]` ids) → confirma: `item_id = proposed_item_id`, **sem** mexer na quantidade; recusa: `proposal_rejected_at = now()`, limpa a proposta.

- [ ] **Step 1: Testes que falham**:
  - `test_proposer_records_confident_matches_only`.
  - `test_confirming_proposals_links_without_changing_quantity`: peça com 7 → continua 7; item sai das pendências.
  - `test_rejected_proposal_is_not_proposed_again`.
  - `test_ai_failure_during_proposals_keeps_items_untouched` (`decide` lança `AiException` → nenhuma proposta, job falha visível).
- [ ] **Step 2:** rodar → FAIL.
- [ ] **Step 3:** Implementar.
- [ ] **Step 4:** `composer check`, `npm run e2e`.
- [ ] **Step 5:** Commit `feat(compras): IA propoe ligar compras antigas as pecas, confirmacao em lote`.

### Task 8: Skill `/importar-pedidos` e parser dos e-mails

Repo: **homelab** (`/home/gui_f/homelab`), não o inventário.

**Files:**
- Create: `.claude/skills/importar-pedidos/SKILL.md`, `.claude/skills/importar-pedidos/parse_aliexpress.py`, `.claude/skills/importar-pedidos/test_parse_aliexpress.py`, `.claude/skills/importar-pedidos/fixtures/` (HTML de e-mail real **com endereço, telefone e nome trocados por valores falsos** antes de salvar)
- Test: `python3 -m pytest .claude/skills/importar-pedidos`

**Interfaces:**
- Consumes: Gmail MCP (`search_threads`, `get_message` com `FULL_CONTENT`; respostas grandes são salvas em arquivo pelo próprio harness e lidas com `jq -r .htmlBody`), comandos `compras:pedidos` e `compras:importar` (Task 2) via `ssh root@100.91.35.23` com `sudo -u www-data php8.4 artisan` em `/var/www/inventario`, como `casa-prea/scripts/inventario.sh`.
- Produces:
  - `parse_aliexpress.py <arquivo.json do get_message>` → imprime um pedido no formato do contrato (sem itens se o e-mail não tiver bloco de itens). Regras:
    - `number` e status do assunto: `Pedido (\d+): (pedido confirmado|aguardando confirmação|pedido enviado|coletado pela transportadora|como foi a experiência\?)` → `confirmed|awaiting|shipped|shipped|delivered`; assuntos de cancelamento/reembolso (conferir os reais no Gmail com `subject:(cancelado OR reembolso)`) → `cancelled|refunded`.
    - itens: só dentro do bloco cujo link é `order/detail.html?orderId=<number>` e que vem **antes** do texto "Você também vai gostar"; título = texto do `<span>` do item, variação e `x<N>`; `image_url` = a `kf/...jpg` do mesmo bloco.
    - `total` de "Total do pedido R$56,79" → `"56.79"`; `ordered_at` = data do e-mail.
    - nunca emite endereço, telefone ou nome; links passam por limpeza de rastreamento.
  - `SKILL.md` (gatilhos: "/importar-pedidos", "importar pedidos do AliExpress"): passos —
    1. `compras:pedidos aliexpress` para saber o que já existe e o status.
    2. `search_threads` `from:transaction@notice.aliexpress.com subject:Pedido` paginado; pelo assunto já se sabe número e status de cada pedido.
    3. Para pedidos novos: buscar **um** e-mail com itens (preferir "pedido confirmado", senão "pedido enviado"), `get_message` FULL_CONTENT, rodar o parser. Pedidos que já existem: só mandar o status novo, sem baixar o e-mail.
    4. Mais de 40 pedidos novos: dividir em subagentes de 40 que devolvem só os caminhos dos JSON gerados (o conteúdo dos e-mails não volta para a conversa principal).
    5. Juntar num `payload.json` no scratchpad, `scp` para `/tmp` do canivete, `compras:importar`, apagar o arquivo remoto.
    6. Relatar pedidos novos, atualizados, itens novos e e-mails que o parser não leu.

- [ ] **Step 1: Testes que falham** (`test_parse_aliexpress.py`, com as fixtures sanitizadas):
  - `test_confirmed_order_has_items_total_and_image`: número `8215761587024729`, status `confirmed`, 1 item `SONOFF ZBMINIR2 Interruptor I...`, variação `ZBMINIR2 1 PC`, quantidade 1, total `"56.79"`, imagem `https://ae-pic-a1.aliexpress-media.com/kf/Sd0743a5e8fb24bee96a9859e995a85c26.jpg`.
  - `test_recommendations_are_not_items`: o e-mail tem 9 `productId=` de propaganda e o resultado continua com 1 item.
  - `test_review_email_means_delivered`.
  - `test_no_personal_data_in_output`: a saída não contém o telefone, a rua nem o nome falsos da fixture.
  - `test_urls_have_no_tracking_params`.
- [ ] **Step 2:** `python3 -m pytest .claude/skills/importar-pedidos` → FAIL.
- [ ] **Step 3:** Implementar parser e `SKILL.md`.
- [ ] **Step 4:** pytest → PASS. Rodada real: `/importar-pedidos` com os pedidos dos últimos 30 dias; conferir na tela Compras do canivete (deploy das Tasks 1–7 feito) que os itens aparecem, enriquecem e não têm dados pessoais (`sqlite3 database/database.sqlite "select * from purchase_orders" | grep -c 'BALEIA'` → 0).
- [ ] **Step 5:** Commit no homelab `feat(inventario): skill importar-pedidos le pedidos do AliExpress no Gmail`.

### Task 9: Deploy e primeira importação completa

**Files:**
- Modify: `docs/ARCHITECTURE.md` (seção Compras: fluxo skill → importer → job → telas; host allowlist), `CHANGELOG.md` (2.1.0)

- [ ] **Step 1:** `scripts/deploy.sh`; no canivete, instalar e ligar `inventario-queue.service` (`systemctl enable --now`); `systemctl status inventario-queue` ativo.
- [ ] **Step 2:** `/importar-pedidos` com o histórico inteiro; conferir contagem de pedidos no Gmail × `select count(*) from purchase_orders`.
- [ ] **Step 3:** Acompanhar o enriquecimento (`select count(*) from purchase_items where enriched_at is null`) até zerar ou só sobrar erro; `compras:enriquecer --falhas` uma vez.
- [ ] **Step 4:** Gerar propostas (`compras:propor`) e abrir `/compras/propostas` para o dono revisar.
- [ ] **Step 5:** Commit `docs(compras): arquitetura e changelog da area de Compras`, push.
