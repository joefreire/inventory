# Contributing

Thank you for your interest in contributing to the Inventory Management System.

## Development Setup

1. Fork and clone the repository
2. Follow the [Installation Guide](INSTALLATION.md)
3. Create a new branch for your feature or bugfix

## Code Style

### PHP

Follow PSR-12 coding standards. Use Laravel Pint for automatic formatting:

```bash
./vendor/bin/pint
```

### Key Guidelines

- Use type hints for all parameters and return types
- Add PHPDoc comments for all public methods
- Keep methods short and focused (max ~20 lines)
- Use meaningful variable and method names

### Example

```php
/**
 * Create a new item with optional tags and photo.
 *
 * @param array<string, mixed> $data Validated item data
 * @param array<string> $tagNames Array of tag names to associate
 * @param UploadedFile|null $photo Photo file to upload
 * @return Item The created item
 */
public function create(array $data, array $tagNames = [], ?UploadedFile $photo = null): Item
{
    $item = Item::create($data);

    if (!empty($tagNames)) {
        $item->syncTags($tagNames);
    }

    if ($photo) {
        $this->uploadPhoto($item, $photo);
    }

    return $item;
}
```

## Architecture Guidelines

### Controllers

- Keep controllers thin
- Use Form Requests for validation
- Delegate business logic to services
- Return appropriate response types

```php
// Good
public function store(StoreItemRequest $request): RedirectResponse
{
    $item = $this->itemService->create($request->validatedWithProcessing());
    return redirect()->route('items.show', $item);
}

// Avoid: Business logic in controller
public function store(Request $request): RedirectResponse
{
    $validated = $request->validate([...]);
    $item = Item::create($validated);
    // ... lots of business logic here
}
```

### Services

- One service per domain area
- Methods should do one thing
- Use dependency injection

### Models

- Use traits for shared behavior
- Define relationships explicitly
- Add scopes for common queries
- Document properties with PHPDoc

## Testing

### Running Tests

```bash
# All tests
php artisan test

# Specific test file
php artisan test tests/Feature/ItemTest.php

# With coverage
php artisan test --coverage

# Playwright E2E tests
npx playwright test
```

### Writing Tests

- Write tests for new features
- Include both happy path and edge cases
- Use factories for test data

```php
public function test_can_create_item_with_tags(): void
{
    $location = Location::factory()->create();

    $response = $this->post(route('items.store'), [
        'name' => 'Test Item',
        'location_id' => $location->id,
        'tags' => 'tag1, tag2, tag3',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('items', ['name' => 'Test Item']);
    $this->assertDatabaseHas('tags', ['name' => 'tag1']);
}
```

## Pull Request Process

1. **Create a feature branch**
   ```bash
   git checkout -b feature/your-feature-name
   ```

2. **Make your changes**
   - Write clean, documented code
   - Add/update tests as needed
   - Run the test suite

3. **Commit with clear messages**
   ```bash
   git commit -m "Add: description of what you added"
   git commit -m "Fix: description of what you fixed"
   git commit -m "Update: description of what you changed"
   ```

4. **Push and create PR**
   ```bash
   git push origin feature/your-feature-name
   ```

5. **PR Description**
   - Describe what the PR does
   - Reference any related issues
   - List any breaking changes

## Issue Guidelines

### Bug Reports

Include:
- Steps to reproduce
- Expected behavior
- Actual behavior
- Environment (PHP version, OS, etc.)

### Feature Requests

Include:
- Use case description
- Proposed solution (if any)
- Alternatives considered

## Code Review

All PRs require review before merging. Reviewers will check:

- Code quality and style
- Test coverage
- Documentation
- Performance implications
- Security considerations

## Questions?

Open an issue with the "question" label if you need help.
