# Inventory Management System

A Laravel-based inventory management system with AI-powered image recognition for electronic components and technical parts.

## Features

- **Item Management**: Create, edit, delete, and search inventory items
- **AI Image Recognition**: Automatically identify electronic components using OpenAI Vision API
- **Hierarchical Locations**: Organize items in a tree structure (rooms, shelves, drawers)
- **Flexible Tagging**: Categorize items with colored tags
- **Photo Management**: Upload and manage item photos with automatic thumbnails
- **Advanced Search**: Filter by location, tag, category, or text search
- **Responsive UI**: Mobile-friendly Bootstrap 5 interface

## Requirements

- PHP 8.2+
- Composer
- Node.js 18+ (for frontend assets)
- SQLite or MySQL/PostgreSQL
- OpenAI API key (for AI features)

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

Visit `http://localhost:8000` in your browser.

## Configuration

### OpenAI API (Optional)

To enable AI image recognition, add your OpenAI API key to `.env`:

```env
OPENAI_API_KEY=your-api-key-here
```

The system uses GPT-4 Vision to identify electronic components from photos.

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
| POST | `/items/analyze` | Analyze image with AI |
| POST | `/items/{id}/move` | Move item to location |
| GET | `/locations` | List all locations |
| POST | `/locations` | Create new location |
| GET | `/locations/tree` | Get location tree (JSON) |
| GET | `/tags` | List all tags |
| POST | `/tags` | Create new tag |
| GET | `/tags/search` | Search tags (JSON) |

See [docs/API.md](docs/API.md) for detailed API documentation.

## Testing

Run PHPUnit tests:
```bash
php artisan test
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

- [Installation Guide](docs/INSTALLATION.md)
- [API Reference](docs/API.md)
- [Architecture](docs/ARCHITECTURE.md)
- [Contributing](docs/CONTRIBUTING.md)

## License

This project is open-sourced software licensed under the [MIT license](LICENSE).
