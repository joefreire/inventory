# Inventory Management System

Inventario pessoal de componentes eletronicos e pecas, com IA (Ollama Cloud) e tags NFC nas caixas.

## Features

- **Item Management**: Create, edit, delete, and search inventory items
- **IA (Ollama Cloud)**: foto -> cadastro sugerido, caixa sugerida, "ja tenho isso?" e busca em linguagem natural
- **Tags NFC**: cada caixa tem um link fixo `/n/{codigo}`; encostar o celular abre a caixa
- **Hierarchical Locations**: Organize items in a tree structure (rooms, shelves, drawers)
- **Flexible Tagging**: Categorize items with colored tags
- **Photo Management**: Upload and manage item photos with automatic thumbnails
- **Advanced Search**: Filter by location, tag, category, or text search
- **Responsive UI**: Mobile-friendly Bootstrap 5 interface

## Requirements

- PHP 8.4+ (na WSL use `php8.4`)
- Composer
- Node.js 18+ (for frontend assets)
- SQLite or MySQL/PostgreSQL
- Chave do Ollama Cloud (`OLLAMA_API_KEY`)

## Installation

1. Clone the repository:
```bash
git clone https://github.com/joefreire/inventory.git
cd inventory
```

2. Install PHP dependencies:
```bash
composer install
```

3. Install Node.js dependencies:
```bash
npm install
```

4. Copy environment file:
```bash
cp .env.example .env
```

5. Generate application key:
```bash
php artisan key:generate
```

6. Configure your database in `.env`:
```env
DB_CONNECTION=sqlite
# Or for MySQL:
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=inventory
# DB_USERNAME=root
# DB_PASSWORD=
```

7. Run migrations:
```bash
php artisan migrate
```

8. Create storage symlink:
```bash
php artisan storage:link
```

9. Build frontend assets:
```bash
npm run build
```

10. Start the development server:
```bash
php artisan serve
```

Visit `http://localhost:8000` in your browser. Crie o usuário com `php8.4 artisan inventario:usuario EMAIL`.

## Configuration

### IA (Ollama Cloud)

```env
OLLAMA_API_KEY=...                                  # https://ollama.com/settings/keys
# AI_VISION_MODELS=gemma4:31b,kimi-k3,glm-5.3-flash   # leem a foto; em ordem, o seguinte entra se um falhar
# AI_DECISION_MODEL=clef                              # modelo de decisao (/v1/systemone)
```

Dois tipos de modelo (`app/Services/Ai/`):

| Etapa | Modelo | O que faz |
|---|---|---|
| Ler a foto | `gemma4:31b` (reserva: `kimi-k3`, `glm-5.3-flash`) | Nome, descricao, specs, texto impresso na peca |
| Decidir | `clef` | Uma chamada com perguntas fechadas: categoria, quais tags existentes servem, em qual caixa guardar, se a peca ja esta cadastrada |
| Perguntar | `clef` | Cada item vira um sim/nao contra a pergunta ("tenho algo pra medir temperatura?") |

O modelo de decisao devolve probabilidades, nao texto. Os limites para usar a
decisao sem perguntar ficam em `config/ai.php` (`thresholds`). Medido em
2026-10-25: foto -> sugestao completa em ~3 s; busca em ~0,6 s.

### Tags NFC

Cada caixa (local) ganha um codigo e o link `NFC_BASE_URL/n/{codigo}`.

```env
NFC_BASE_URL=https://inventario.exemplo   # endereco fixo: definir ANTES de gravar a primeira tag
```

- **Abrir a caixa:** encostar o celular na tag (Android e iPhone abrem o link sozinhos).
- **Gravar a tag:** pagina da caixa > "Gravar na tag" (Chrome no Android, so em HTTPS).
  No iPhone: "Copiar" o link e gravar no app NFC Tools (Gravar > Adicionar registro > URL).
- **Guardar um item:** na pagina do item, "Guardar: encostar na caixa" (Android), ou
  na pagina da caixa aberta pela tag, "Guardar item aqui" (qualquer celular).
- Tags NTAG213/215 servem; o link tem ~40 caracteres.

### Media Storage

Photos are stored in `storage/app/public/`. Ensure the storage symlink is created:

```bash
php artisan storage:link
```

## Usage

### Items

- **Create**: Click "New Item" and fill in the details
- **AI Analyze**: Upload a photo to automatically identify the component
- **Edit**: Click on an item to view/edit details
- **Move**: Drag items between locations or use the move button
- **Search**: Use the search bar to find items by name, description, or category

### Locations

- **Create**: Add new storage locations (rooms, shelves, etc.)
- **Hierarchy**: Create sub-locations to organize your space
- **Browse**: Click on a location to see its items

### Tags

- **Create**: Add tags with custom colors
- **Assign**: Add multiple tags to items for flexible categorization
- **Filter**: Click on a tag to see all related items

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/items` | List all items (with filters) |
| POST | `/items` | Create new item |
| GET | `/items/{id}` | View item details |
| PUT | `/items/{id}` | Update item |
| DELETE | `/items/{id}` | Delete item |
| POST | `/items/analyze` | Sugestao da IA (foto e/ou nome) |
| GET | `/items/ask?q=` | Busca em linguagem natural |
| GET | `/n/{codigo}` | Link da tag NFC: abre a caixa |
| POST | `/items/{id}/nfc-move` | Guarda o item na caixa da tag lida |
| POST | `/items/{id}/move` | Move item to location |
| GET | `/locations` | List all locations |
| POST | `/locations` | Create new location |
| GET | `/locations/tree` | Get location tree (JSON) |
| GET | `/tags` | List all tags |
| POST | `/tags` | Create new tag |
| GET | `/tags/search` | Search tags (JSON) |

See [docs/API.md](docs/API.md) for detailed API documentation.

## Testing

Run PHPUnit tests (a IA e simulada com `Http::fake`):
```bash
php8.4 artisan test
```

Run Playwright E2E tests:
```bash
npx playwright test
```

## Project Structure

```
app/
├── Http/
│   ├── Controllers/     # Request handlers
│   └── Requests/        # Form validation
├── Models/              # Eloquent models
│   └── Traits/          # Reusable model traits
└── Services/            # Business logic
docs/                    # Documentation
resources/views/         # Blade templates
tests/
├── Feature/             # Feature tests
└── Unit/                # Unit tests
```

## Documentation

- [Arquitetura, segurança e escala](docs/ARCHITECTURE.md)

## License

This project is open-sourced software licensed under the [MIT license](LICENSE).

## Producao (canivete)

`https://inventario.runningwithjoe.com`, no servidor canivete (`root@100.91.35.23`, Tailscale).

| Peca | Onde |
|---|---|
| App | `/var/www/inventario` (dono `www-data`), SQLite em `database/database.sqlite`, fotos em `storage/app/public` |
| PHP | pool `inventario` do `php8.4-fpm` (`/etc/php/8.4/fpm/pool.d/inventario.conf`). O `php` padrao do servidor continua 8.3: use `php8.4` |
| Web | nginx `/etc/nginx/sites-available/inventario.conf`, so em `127.0.0.1:8182` |
| Tunel | `cloudflared-inventario.service`, tunel `inventario-canivete` (`/etc/cloudflared/inventario.yml`) |
| DNS | `cloudflared --origincert /root/.cloudflared/cert-runningwithjoe.pem tunnel route dns ...` (o `cert.pem` padrao do canivete so cobre opiniaotech.com) |

Arquitetura, segurança e escala: [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

Usuario (nao ha cadastro pela tela):
```bash
ssh -t root@100.91.35.23 'cd /var/www/inventario && sudo -u www-data php8.4 artisan inventario:usuario EMAIL'
```

Atualizar: `scripts/deploy.sh` (roda `composer check`, gera o front, envia e migra;
faz cópia do banco antes).

O inventario anterior (FastAPI, agosto/2026) esta em `joefreire/inventario-eletronicos` e
ainda roda no LXC 105 do Proxmox, sem dominio. As 2 pecas e 3 caixas reais dele foram
migradas para ca em 2026-10-25.
