# Architecture

This document describes the system architecture and design decisions.

## Overview

The Inventory Management System follows a traditional MVC (Model-View-Controller) architecture with additional service layer for complex business logic.

```
┌─────────────────────────────────────────────────────────────┐
│                        Client                                │
│                    (Web Browser)                             │
└─────────────────────────────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────┐
│                      HTTP Layer                              │
│                  (Laravel Router)                            │
└─────────────────────────────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────┐
│                     Controllers                              │
│          ItemController, LocationController, etc.            │
└─────────────────────────────────────────────────────────────┘
                             │
              ┌──────────────┼──────────────┐
              ▼              ▼              ▼
┌─────────────────┐ ┌─────────────┐ ┌─────────────────┐
│  Form Requests  │ │  Services   │ │     Models      │
│   (Validation)  │ │  (Logic)    │ │   (Eloquent)    │
└─────────────────┘ └─────────────┘ └─────────────────┘
                             │              │
                             └──────┬───────┘
                                    ▼
┌─────────────────────────────────────────────────────────────┐
│                       Database                               │
│                (SQLite / MySQL / PostgreSQL)                 │
└─────────────────────────────────────────────────────────────┘
```

## Design Principles

### Single Responsibility Principle (SRP)

Each class has one reason to change:

- **Controllers**: Handle HTTP requests and responses
- **Form Requests**: Validate incoming data
- **Services**: Contain business logic
- **Models**: Represent data and relationships

### Don't Repeat Yourself (DRY)

Common code is extracted into reusable components:

- **Traits**: `HasPhotoUrls`, `HasDescendants` for shared model behavior
- **Form Requests**: Validation rules shared between store/update
- **Services**: Business logic reused across controllers

### Keep It Simple, Stupid (KISS)

- Simple controller methods that delegate to services
- Straightforward data flow
- Minimal dependencies between components

## Directory Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── ItemController.php       # Item CRUD + analyze + move
│   │   ├── LocationController.php   # Location CRUD + tree
│   │   └── TagController.php        # Tag CRUD + search
│   └── Requests/
│       ├── StoreItemRequest.php     # Item creation validation
│       ├── UpdateItemRequest.php    # Item update validation
│       ├── StoreLocationRequest.php # Location creation validation
│       ├── UpdateLocationRequest.php# Location update + cycle check
│       ├── StoreTagRequest.php      # Tag creation validation
│       └── UpdateTagRequest.php     # Tag update validation
├── Models/
│   ├── Item.php                     # Item model with scopes
│   ├── Location.php                 # Location with tree methods
│   ├── Tag.php                      # Tag with color generation
│   └── Traits/
│       ├── HasPhotoUrls.php         # Photo URL accessor methods
│       └── HasDescendants.php       # Tree traversal methods
└── Services/
    ├── ItemService.php              # Item business logic
    ├── LocationService.php          # Location tree operations
    └── OpenAIVisionService.php      # AI image analysis
```

## Data Model

### Entity Relationship Diagram

```
┌──────────────┐       ┌──────────────┐       ┌──────────────┐
│   Location   │       │     Item     │       │     Tag      │
├──────────────┤       ├──────────────┤       ├──────────────┤
│ id           │───┐   │ id           │   ┌───│ id           │
│ parent_id    │   │   │ location_id  │◄──┘   │ name         │
│ name         │   └──►│ name         │       │ color        │
│ description  │       │ description  │       └──────────────┘
│ path         │       │ ai_description│             ▲
│ depth        │       │ category     │             │
└──────────────┘       │ quantity     │       ┌──────────────┐
      │                │ notes        │       │   item_tag   │
      │                │ ai_metadata  │       ├──────────────┤
      ▼                └──────────────┘       │ item_id      │◄──┐
┌──────────────┐              │              │ tag_id       │   │
│   Location   │              │              └──────────────┘   │
│  (children)  │              └─────────────────────────────────┘
└──────────────┘

┌──────────────┐
│    Media     │
├──────────────┤
│ id           │
│ model_id     │◄─── Item.id
│ model_type   │
│ collection   │
│ file_name    │
│ disk         │
└──────────────┘
```

### Tables

#### items
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| location_id | bigint | Foreign key to locations |
| name | varchar(255) | Item name |
| description | text | User description |
| ai_description | text | AI-generated description |
| category | varchar(100) | Item category |
| quantity | integer | Stock quantity |
| notes | text | Additional notes |
| ai_metadata | json | AI analysis data |
| created_at | timestamp | |
| updated_at | timestamp | |

#### locations
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| parent_id | bigint | Self-referencing FK |
| name | varchar(255) | Location name |
| description | text | Location description |
| path | varchar(500) | Full path (auto-generated) |
| depth | integer | Tree depth (auto-calculated) |
| created_at | timestamp | |
| updated_at | timestamp | |

#### tags
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| name | varchar(100) | Tag name (unique) |
| color | varchar(7) | Hex color (#RRGGBB) |
| created_at | timestamp | |
| updated_at | timestamp | |

#### item_tag (pivot)
| Column | Type | Description |
|--------|------|-------------|
| item_id | bigint | Foreign key to items |
| tag_id | bigint | Foreign key to tags |
| created_at | timestamp | |
| updated_at | timestamp | |

## Request Flow

### Creating an Item with Photo

```
1. User submits form
       │
       ▼
2. StoreItemRequest validates data
       │
       ▼
3. ItemController::store() receives validated data
       │
       ▼
4. ItemService::create() handles business logic
       │
       ├──► Item::create() saves to database
       │
       ├──► Item::syncTags() creates/associates tags
       │
       └──► Spatie Media Library saves photo
                │
                └──► Creates thumb + preview conversions
       │
       ▼
5. Redirect to item show page
```

### AI Image Analysis

```
1. User uploads photo via AJAX
       │
       ▼
2. ItemController::analyze() validates file
       │
       ▼
3. OpenAIVisionService::analyzeUploadedFile()
       │
       ├──► Converts image to base64
       │
       └──► Sends to OpenAI Vision API
                │
                ▼
           GPT-4 Vision analyzes image
                │
                ▼
           Returns JSON with:
           - name
           - description
           - category
           - suggested_tags
           - specs
           - confidence
       │
       ▼
4. JSON response to client
       │
       ▼
5. JavaScript populates form fields
```

## Key Patterns

### Form Request Pattern

Validation logic is extracted from controllers into dedicated request classes:

```php
// Before: Validation in controller
public function store(Request $request)
{
    $validated = $request->validate([...]);
    // ...
}

// After: Dedicated form request
public function store(StoreItemRequest $request)
{
    $item = $this->itemService->create($request->validatedWithProcessing());
    // ...
}
```

### Service Layer Pattern

Complex business logic is moved to service classes:

```php
// ItemService handles:
// - Creating items with tags and photos
// - Updating items with photo management
// - Moving items between locations
```

### Trait Pattern

Shared model behavior is extracted into traits:

```php
class Item extends Model
{
    use HasPhotoUrls;  // getPhotoUrl(), getMainPhotoUrlAttribute(), etc.
}

class Location extends Model
{
    use HasDescendants;  // getDescendantIds(), isDescendant(), canHaveParent()
}
```

## External Dependencies

### Spatie Media Library

Handles file uploads with:
- Multiple conversions (thumb, preview)
- Disk storage abstraction
- Relationship polymorphism

### OpenAI Laravel

Integration with OpenAI API for:
- GPT-4 Vision image analysis
- Component identification
- Automatic metadata extraction
