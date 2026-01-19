# API Reference

This document describes the REST API endpoints for the Inventory Management System.

## Base URL

```
http://localhost:8000
```

## Authentication

Currently, the API does not require authentication. For production use, consider implementing Laravel Sanctum or Passport.

## Items

### List Items

```http
GET /items
```

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| search | string | Search in name, description, notes |
| location_id | integer | Filter by location ID |
| tag_id | integer | Filter by tag ID |
| category | string | Filter by category |
| page | integer | Page number for pagination |

**Response:**
```json
{
    "data": [
        {
            "id": 1,
            "name": "Capacitor 100uF",
            "description": "Electrolytic capacitor",
            "category": "capacitor",
            "quantity": 10,
            "location": {
                "id": 1,
                "name": "Drawer 1",
                "path": "Office/Shelf A/Drawer 1"
            },
            "tags": [
                {"id": 1, "name": "SMD", "color": "#0d6efd"}
            ],
            "main_photo_url": "/storage/1/conversions/thumb.jpg"
        }
    ],
    "links": {...},
    "meta": {...}
}
```

### Create Item

```http
POST /items
```

**Request Body (multipart/form-data):**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| name | string | Yes | Item name (max 255 chars) |
| description | string | No | Item description |
| category | string | No | Category (max 100 chars) |
| quantity | integer | No | Quantity (default: 1) |
| location_id | integer | No | Location ID |
| notes | string | No | Additional notes |
| tags | string | No | Comma-separated tag names |
| photo | file | No | Image file (max 10MB) |
| ai_metadata | json | No | AI-generated metadata |

**Response:** Redirect to item show page

### View Item

```http
GET /items/{id}
```

**Response:** HTML page with item details

### Update Item

```http
PUT /items/{id}
```

**Request Body:** Same as Create Item, plus:

| Field | Type | Description |
|-------|------|-------------|
| remove_photo | boolean | Remove existing photo |

**Response:** Redirect to item show page

### Delete Item

```http
DELETE /items/{id}
```

**Response:** Redirect to items index

### Analyze Image

```http
POST /items/analyze
```

**Request Body (multipart/form-data):**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| photo | file | Yes | Image file (max 10MB) |

**Response:**
```json
{
    "success": true,
    "data": {
        "name": "Capacitor Eletrolitico 100uF 25V",
        "description": "Capacitor eletrolitico de 100 microfarads",
        "category": "capacitor",
        "suggested_tags": ["eletrolitico", "100uf", "25v"],
        "specs": {
            "valor": "100uF",
            "tensao": "25V",
            "encapsulamento": "Through-hole"
        },
        "confidence": 0.92
    }
}
```

### Move Item

```http
POST /items/{id}/move
```

**Request Body:**
```json
{
    "location_id": 5
}
```

**Response:**
```json
{
    "success": true,
    "message": "Item movido com sucesso!"
}
```

## Locations

### List Locations

```http
GET /locations
```

**Response:** HTML page with location tree

### Create Location

```http
POST /locations
```

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| name | string | Yes | Location name (max 255 chars) |
| description | string | No | Location description |
| parent_id | integer | No | Parent location ID |

**Response:** Redirect to locations index

### View Location

```http
GET /locations/{id}
```

**Response:** HTML page with location details and items

### Update Location

```http
PUT /locations/{id}
```

**Request Body:** Same as Create Location

**Response:** Redirect to locations index

### Delete Location

```http
DELETE /locations/{id}
```

**Response:** Redirect to locations index

### Get Location Tree

```http
GET /locations/tree
```

**Response:**
```json
[
    {
        "id": 1,
        "name": "Office",
        "path": "Office",
        "depth": 0,
        "item_count": 5,
        "children": [
            {
                "id": 2,
                "name": "Shelf A",
                "path": "Office/Shelf A",
                "depth": 1,
                "item_count": 12,
                "children": []
            }
        ]
    }
]
```

## Tags

### List Tags

```http
GET /tags
```

**Response:** HTML page with paginated tag list

### Create Tag

```http
POST /tags
```

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| name | string | Yes | Tag name (max 100 chars, unique) |
| color | string | No | Hex color code (#RRGGBB) |

**Response (HTML):** Redirect to tags index

**Response (JSON):**
```json
{
    "success": true,
    "tag": {
        "id": 1,
        "name": "SMD",
        "color": "#0d6efd"
    }
}
```

### View Tag

```http
GET /tags/{id}
```

**Response:** HTML page with tag details and tagged items

### Update Tag

```http
PUT /tags/{id}
```

**Request Body:** Same as Create Tag

**Response:** Redirect to tags index

### Delete Tag

```http
DELETE /tags/{id}
```

**Response:** Redirect to tags index

### Search Tags

```http
GET /tags/search
```

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| q | string | Search query |

**Response:**
```json
[
    {"id": 1, "name": "SMD", "color": "#0d6efd"},
    {"id": 2, "name": "Through-hole", "color": "#198754"}
]
```

## Error Responses

### Validation Error (422)

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "name": ["The name field is required."]
    }
}
```

### Not Found (404)

```json
{
    "message": "No query results for model [App\\Models\\Item] 999"
}
```

### Server Error (500)

```json
{
    "success": false,
    "message": "Error description"
}
```
