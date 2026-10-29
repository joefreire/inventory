# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.1.0] - 2026-10-29

### Added

- Area "Compras": pedidos do AliExpress importados pela skill `/importar-pedidos`
  (le o Gmail fora do app) via `compras:importar` ou upload em `/compras/importar`.
  Importacao idempotente, status so avanca; endereco e telefone nunca sao gravados.
- Enriquecimento por IA em fila (`EnrichPurchaseItem`): nome completo, specs e
  pacote a partir da foto oficial (baixada so de hosts permitidos, HTTPS, sem
  redirect) e do nome cortado; sem foto, so pelo texto.
- Lista de pendentes, com cadastrar peca, juntar a peca existente e dispensar.
- `PurchaseDecision`: o cadastro sugere de qual compra veio a peca.
- Ligacao em lote das compras antigas as pecas (`LinkProposer`); confirmar nao
  muda a quantidade.
- Comandos `compras:pedidos`, `compras:enriquecer` e `compras:propor`.
- Worker `inventario-queue` (`deploy/inventario-queue.service`).
- Botao "Sair" e acesso a Tags no menu da tela de pecas.

### Changed

- `.env` de producao precisa de `DB_QUEUE_RETRY_AFTER=660`.
- Testes E2E reescritos para a interface nova.
- Refactor: IA modular, HTTP enxuto, seguranca e front em modulos.

### Fixed

- Fotos sem arquivo orfao, IA que falha calada e HEIC recusado com mensagem honesta.
- Categoria fora da config nao trava a edicao; busca ve mudanca de inventario.
- Compras: ligar so uma vez e sem somar em dobro; atualizacao so de status nao apaga
  dados do pedido; IA da compra com a foto de 800 px; nao propor ligar o que nao e peca.
- `symfony/mime` 7.4.22 (CVE-2026-45070).

## [2.0.0] - 2026-10-25

### Changed

- IA trocada de OpenAI para Ollama Cloud: modelos de visao em cadeia
  (`gemma4:31b`, `kimi-k3`, `glm-5.3-flash`) e o modelo de decisao `clef`.
- Sem chave ou com a IA fora, a analise responde erro (antes respondia
  "Item nao identificado" com sucesso e o erro passava despercebido).

### Added

- Sugestao completa numa chamada de decisao: categoria, tags existentes,
  caixa onde guardar e aviso "voce ja tem isso?".
- Sugestao sem foto, so pelo nome digitado.
- Pagina "Perguntar": busca em linguagem natural.
- Tags NFC nas caixas: codigo por local, link `/n/{codigo}`, gravacao pelo
  Web NFC (Android), "Guardar: encostar na caixa" e "Guardar item aqui".

### Removed

- `openai-php/laravel` e `OpenAIVisionService`.

## [1.0.0] - 2026-01-19

### Added

- **Item Management**
  - Create, read, update, delete inventory items
  - Photo upload with automatic thumbnails (200x200) and previews (800x800)
  - AI-powered image analysis using OpenAI Vision API
  - Search by name, description, category, and notes
  - Filter by location, tag, or category
  - Move items between locations

- **Location Management**
  - Hierarchical location tree (rooms, shelves, drawers)
  - Automatic path and depth calculation
  - Parent-child relationship with cycle prevention
  - Location browsing with item counts

- **Tag System**
  - Flexible tagging with custom colors
  - Auto-create tags when assigning to items
  - Tag search with autocomplete
  - Random color generation from predefined palette

- **AI Features**
  - OpenAI Vision integration for component identification
  - Automatic extraction of specs (value, voltage, package)
  - Confidence scoring for identifications
  - Suggested tags based on component analysis

- **User Interface**
  - Responsive Bootstrap 5 design
  - Mobile-friendly layout
  - Real-time form validation
  - Toast notifications for actions

### Technical

- Laravel 11 framework
- PHP 8.2+ compatibility
- Spatie Media Library for file handling
- SQLite/MySQL/PostgreSQL database support
- PHPUnit test suite
- Playwright E2E tests

### Architecture

- Service layer pattern for business logic
- Form Request classes for validation
- Model traits for shared behavior (HasPhotoUrls, HasDescendants)
- Comprehensive PHPDoc documentation
