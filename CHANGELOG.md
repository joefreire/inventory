# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
