# REST API Documentation

The SocialTokenizator REST API allows you to programmatically create, list, and delete posts with image uploads. Use it to integrate with automation tools like **n8n**, **Zapier**, or custom scripts.

---

## Table of Contents

1. [Authentication](#authentication)
2. [Base URL](#base-url)
3. [Endpoints](#endpoints)
   - [Get All Categories](#1-get-all-categories)
   - [Create a Post](#2-create-a-post)
   - [List Posts](#3-list-posts)
   - [Get a Single Post](#4-get-a-single-post)
   - [Delete a Post](#5-delete-a-post)
4. [Error Responses](#error-responses)
5. [n8n Integration Examples](#n8n-integration-examples)
   - [Get all categories](#n8n-example-1-get-all-categories)
   - [Create a post with an image file](#n8n-example-2-create-a-post-with-an-image-file)
   - [Create a post from an image URL](#n8n-example-3-create-a-post-from-an-image-url)
   - [List posts filtered by category](#n8n-example-4-list-posts-by-category)
   - [Paginate through all posts](#n8n-example-5-paginate-through-all-posts)
   - [Delete a post by UUID](#n8n-example-6-delete-a-post)

---

## Authentication

All API requests require a Bearer token, passed in the `Authorization` header:

```
Authorization: Bearer <your-api-token>
```

### Obtaining a Token

1. Log in to the admin panel (`/admin`).
2. Go to **Settings**.
3. Scroll to the **API Token** section.
4. Click **Generate Token** (or **Regenerate** to replace an existing one).
5. Copy the token — it will only be shown in full immediately after generation. You can copy it again later using the **Copy** button, but the full token is always available to the admin.

> ⚠️ **Regenerating the token invalidates the previous one.** All API clients must be updated.

### IP Address Allowlist

By default, the API accepts requests from any IP address. To restrict access, configure an allowlist in **Settings → RestAPI**.

- Add one address per line.
- Supports IPv4 and IPv6, single addresses or CIDR ranges.
- Leave the field empty to allow any IP.

```
203.0.113.10
203.0.113.0/24
2001:db8::1
2001:db8::/48
```

Requests from IPs not on the list receive `403 Forbidden`.

---

## Base URL

```
https://<your-domain>/api
```

All endpoints are relative to this base.

---

## Endpoints

### 1. Get All Categories

```
GET /api/categories
```

Returns the list of all categories. Useful for discovering available categories before creating or filtering posts.

#### Example Request (curl)

```bash
curl -X GET "https://example.com/api/categories" \
  -H "Authorization: Bearer YOUR_API_TOKEN"
```

#### Example Response (200 OK)

```json
{
  "data": [
    {
      "id": 1,
      "handle": "landscape",
      "name_en_US": "Landscape",
      "name_es_MX": "Paisaje",
      "name_pt_BR": "Paisagem"
    },
    {
      "id": 2,
      "handle": "tech",
      "name_en_US": "Technology",
      "name_es_MX": "Tecnología",
      "name_pt_BR": "Tecnologia"
    }
  ]
}
```

---

### 2. Create a Post

```
POST /api/posts
Content-Type: multipart/form-data
```

Creates a new post with an image. You must provide **either** `image` (file upload) **or** `image_url` (public URL to download) — but not necessarily both. All other fields are optional.

#### Request Body

| Field | Type | Required | Description |
|---|---|---|---|
| `image` | File | **One of these is required** | Image file (JPG, PNG, WebP, GIF, AVIF — max 10 MB) |
| `image_url` | String | **One of these is required** | Publicly-accessible image URL to download and host locally |
| `headline_en_US` | String | No | Headline in English (max 300 chars) |
| `headline_es_MX` | String | No | Headline in Spanish (max 300 chars) |
| `headline_pt_BR` | String | No | Headline in Brazilian Portuguese (max 300 chars) |
| `description_en_US` | String | No | Description in English (max 5000 chars) |
| `description_es_MX` | String | No | Description in Spanish (max 5000 chars) |
| `description_pt_BR` | String | No | Description in Brazilian Portuguese (max 5000 chars) |
| `categories` | String | No | Comma-separated category handles (e.g. `tech,gaming`) or IDs (e.g. `1,3`) |
| `tags` | String | No | Comma-separated tags (e.g. `ai,opensource,linux`) |
| `sources` | String (JSON) | No | JSON array of `{url, label?}` objects |

> **About `image_url`:** The server downloads the image from the provided URL, validates it (MIME type, size ≤ 10 MB), and stores it in the same R2 bucket as file uploads. This avoids hotlinking — the image is self-hosted. If both `image` and `image_url` are sent, the file upload (`image`) takes precedence.

#### Example Request — File Upload (curl)

```bash
curl -X POST https://example.com/api/posts \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -F "image=@/path/to/photo.jpg" \
  -F "headline_en_US=My Awesome Photo" \
  -F "description_en_US=A beautiful sunset over the mountains." \
  -F 'categories=landscape,nature' \
  -F 'tags=sunset,mountains,hiking' \
  -F 'sources=[{"url":"https://unsplash.com/photos/abc","label":"Unsplash"}]'
```

#### Example Request — Image URL (curl)

```bash
curl -X POST https://example.com/api/posts \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -F "image_url=https://example.com/photo.jpg" \
  -F "headline_en_US=My Awesome Photo" \
  -F "description_en_US=A beautiful sunset over the mountains." \
  -F 'categories=landscape,nature' \
  -F 'tags=sunset,mountains,hiking'
```

#### Example Response (201 Created)

```json
{
  "uuid": "550e8400-e29b-41d4-a716-446655440000",
  "public_url": "https://images.example.com/images/550e8400-e29b-41d4-a716-446655440000.jpg",
  "original_filename": "photo.jpg",
  "mime_type": "image/jpeg",
  "width": 1920,
  "height": 1080,
  "headline_en_US": "My Awesome Photo",
  "headline_es_MX": null,
  "headline_pt_BR": null,
  "description_en_US": "A beautiful sunset over the mountains.",
  "description_es_MX": null,
  "description_pt_BR": null,
  "categories": [
    {"id": 2, "handle": "landscape", "name_en_US": "Landscape", "name_es_MX": "Paisaje", "name_pt_BR": "Paisagem"},
    {"id": 5, "handle": "nature", "name_en_US": "Nature", "name_es_MX": "Naturaleza", "name_pt_BR": "Natureza"}
  ],
  "tags": [
    {"id": 10, "name": "sunset"},
    {"id": 11, "name": "mountains"},
    {"id": 12, "name": "hiking"}
  ],
  "sources": [
    {"id": 7, "label": "Unsplash", "url": "https://unsplash.com/photos/abc", "position": 0}
  ],
  "created_at": "2026-08-06T01:30:00.000000Z",
  "updated_at": "2026-08-06T01:30:00.000000Z"
}
```

---

### 3. List Posts

```
GET /api/posts
```

Returns a paginated list of posts. Supports filtering by category, tag, and text search.

#### Query Parameters

| Parameter | Type | Default | Description |
|---|---|---|---|
| `per_page` | Integer | `50` | Number of posts per page (max 100) |
| `page` | Integer | `1` | Page number for pagination |
| `limit` | Integer | — | Absolute limit of posts to return (max 1000). When used, pagination metadata is omitted and a simple `count` is returned instead. |
| `category` | String | — | Filter by category handle (e.g. `tech`) |
| `tag` | String | — | Filter by tag slug (e.g. `ai`) |
| `search` | String | — | Search across headlines and descriptions (and matching tags) |
| `sort` | String | `latest` | Sort order: `latest` (newest first) or `oldest` (oldest first) |

> **Note:** `limit` and `page`/`per_page` are mutually exclusive. If `limit` is provided, the response uses a simple count format without pagination links.

#### Example Requests (curl)

**Paginated list (default 50 per page):**
```bash
curl -X GET "https://example.com/api/posts?per_page=10&page=1" \
  -H "Authorization: Bearer YOUR_API_TOKEN"
```

**Last 100 posts (simple limit):**
```bash
curl -X GET "https://example.com/api/posts?limit=100&sort=latest" \
  -H "Authorization: Bearer YOUR_API_TOKEN"
```

**Filter by category and search:**
```bash
curl -X GET "https://example.com/api/posts?category=tech&search=ai&per_page=20" \
  -H "Authorization: Bearer YOUR_API_TOKEN"
```

#### Example Response (Paginated)

```json
{
  "data": [
    {
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "public_url": "https://images.example.com/images/550e8400-....jpg",
      "original_filename": "photo.jpg",
      "mime_type": "image/jpeg",
      "width": 1920,
      "height": 1080,
      "headline_en_US": "AI Conference 2026",
      "headline_es_MX": null,
      "headline_pt_BR": null,
      "description_en_US": "Keynote presentation at the AI conference.",
      "description_es_MX": null,
      "description_pt_BR": null,
      "categories": [{"id": 1, "handle": "tech", "name_en_US": "Technology", "name_es_MX": "Tecnología", "name_pt_BR": "Tecnologia"}],
      "tags": [{"id": 1, "name": "ai"}, {"id": 2, "name": "conference"}],
      "sources": [],
      "created_at": "2026-08-05T12:00:00.000000Z",
      "updated_at": "2026-08-05T12:00:00.000000Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 10,
    "total": 47,
    "from": 1,
    "to": 10
  },
  "links": {
    "first": "https://example.com/api/posts?page=1",
    "last": "https://example.com/api/posts?page=5",
    "prev": null,
    "next": "https://example.com/api/posts?page=2"
  }
}
```

#### Example Response (Simple Limit)

When using `limit`, the response has a simpler structure:

```json
{
  "data": [ /* ... posts ... */ ],
  "count": 100,
  "limit": 100
}
```

---

### 4. Get a Single Post

```
GET /api/posts/{uuid}
```

Returns a single post by its UUID.

#### Example Request (curl)

```bash
curl -X GET "https://example.com/api/posts/550e8400-e29b-41d4-a716-446655440000" \
  -H "Authorization: Bearer YOUR_API_TOKEN"
```

#### Example Response (200 OK)

Same structure as a single item in the `data` array from the list endpoint.

#### Error Response (404)

```json
{"error": "Post not found."}
```

---

### 5. Delete a Post

```
DELETE /api/posts/{uuid}
```

Permanently deletes a post and its image from storage.

#### Example Request (curl)

```bash
curl -X DELETE "https://example.com/api/posts/550e8400-e29b-41d4-a716-446655440000" \
  -H "Authorization: Bearer YOUR_API_TOKEN"
```

#### Response (204 No Content)

Empty body. HTTP status `204`.

#### Error Response (404)

```json
{"error": "Post not found."}
```

---

## Error Responses

All errors follow a consistent JSON format:

| Status | Meaning |
|---|---|
| `401` | Missing or invalid API token |
| `403` | IP address not allowed (when an IP allowlist is configured) |
| `404` | Post not found |
| `422` | Validation error (e.g. missing image, invalid file type) |

### 401 — Unauthorized

```json
{"error": "Missing API token. Provide it as: Authorization: Bearer <token>"}
```

### 403 — IP Not Allowed

```json
{"error": "IP address is not allowed to access the API."}
```

### 422 — Validation Error

```json
{
  "error": "Validation failed.",
  "messages": {
    "image": ["The image field is required."],
    "headline_en_US": ["The headline en_US must not be greater than 300 characters."]
  }
}
```

---

## n8n Integration Examples

### n8n Example 1: Get All Categories

Fetches the full list of categories. Use this to populate dropdowns or discover available category handles before creating posts.

#### HTTP Request Node Configuration

| Setting | Value |
|---|---|
| **Method** | GET |
| **URL** | `https://your-domain.com/api/categories` |
| **Authentication** | Header Auth |
| **Header Name** | `Authorization` |
| **Header Value** | `Bearer YOUR_API_TOKEN` |

#### n8n JSON Import

```json
{
  "name": "Get All Categories",
  "nodes": [
    {
      "parameters": {
        "method": "GET",
        "url": "https://your-domain.com/api/categories",
        "authentication": "genericCredentialType",
        "genericAuthType": "httpHeaderAuth",
        "sendHeaders": true,
        "headerParameters": {
          "parameters": [
            {"name": "Authorization", "value": "Bearer YOUR_API_TOKEN"}
          ]
        },
        "options": {}
      },
      "name": "HTTP Request",
      "type": "n8n-nodes-base.httpRequest",
      "position": [250, 300]
    }
  ]
}
```

---

### n8n Example 2: Create a Post with an Image File

This workflow uploads an image file (binary data from a previous node) to your SocialTokenizator instance.

#### HTTP Request Node Configuration

| Setting | Value |
|---|---|
| **Method** | POST |
| **URL** | `https://your-domain.com/api/posts` |
| **Authentication** | Header Auth |
| **Header Name** | `Authorization` |
| **Header Value** | `Bearer YOUR_API_TOKEN` |
| **Body Content Type** | Form-Data |
| **Parameters** | |

**Form fields:**

| Name | Type | Value |
|---|---|---|
| `image` | File | `{{ $json.image }}` (or binary data from HTTP Request / Read Binary File) |
| `headline_en_US` | Text | `My n8n Post` |
| `description_en_US` | Text | `Posted automatically via n8n workflow.` |
| `categories` | Text | `automation` |
| `tags` | Text | `n8n,automated` |

#### n8n JSON Import

```json
{
  "name": "Create SocialTokenizator Post (File Upload)",
  "nodes": [
    {
      "parameters": {
        "method": "POST",
        "url": "https://your-domain.com/api/posts",
        "authentication": "genericCredentialType",
        "genericAuthType": "httpHeaderAuth",
        "sendHeaders": true,
        "headerParameters": {
          "parameters": [
            {"name": "Authorization", "value": "Bearer YOUR_API_TOKEN"}
          ]
        },
        "sendBody": true,
        "bodyParameters": {
          "parameters": [
            {"name": "image", "parameterType": "formBinaryData", "inputDataFieldName": "data"},
            {"name": "headline_en_US", "value": "My n8n Post"},
            {"name": "description_en_US", "value": "Posted automatically via n8n workflow."},
            {"name": "categories", "value": "automation"},
            {"name": "tags", "value": "n8n,automated"}
          ]
        },
        "options": {}
      },
      "name": "HTTP Request",
      "type": "n8n-nodes-base.httpRequest",
      "position": [250, 300]
    }
  ]
}
```

---

### n8n Example 3: Create a Post from an Image URL

This workflow sends an image URL directly — the server downloads and hosts it. No need for a separate download step.

#### HTTP Request Node Configuration

| Setting | Value |
|---|---|
| **Method** | POST |
| **URL** | `https://your-domain.com/api/posts` |
| **Authentication** | Header Auth |
| **Header Name** | `Authorization` |
| **Header Value** | `Bearer YOUR_API_TOKEN` |
| **Body Content Type** | Form-Data |
| **Parameters** | |

**Form fields:**

| Name | Type | Value |
|---|---|---|
| `image_url` | Text | `{{ $json.image_url }}` (e.g. `https://example.com/photo.jpg`) |
| `headline_en_US` | Text | `My n8n Post` |
| `description_en_US` | Text | `Posted automatically via n8n workflow.` |
| `categories` | Text | `automation` |
| `tags` | Text | `n8n,automated` |

#### n8n JSON Import

```json
{
  "name": "Create Post from Image URL",
  "nodes": [
    {
      "parameters": {
        "method": "POST",
        "url": "https://your-domain.com/api/posts",
        "authentication": "genericCredentialType",
        "genericAuthType": "httpHeaderAuth",
        "sendHeaders": true,
        "headerParameters": {
          "parameters": [
            {"name": "Authorization", "value": "Bearer YOUR_API_TOKEN"}
          ]
        },
        "sendBody": true,
        "bodyParameters": {
          "parameters": [
            {"name": "image_url", "value": "={{ $json.image_url }}"},
            {"name": "headline_en_US", "value": "My n8n Post"},
            {"name": "description_en_US", "value": "Posted automatically via n8n workflow."},
            {"name": "categories", "value": "automation"},
            {"name": "tags", "value": "n8n,automated"}
          ]
        },
        "options": {}
      },
      "name": "HTTP Request",
      "type": "n8n-nodes-base.httpRequest",
      "position": [250, 300]
    }
  ]
}
```

> **Tip:** The server downloads the image, validates it, and stores it in R2 — no hotlinking. If both `image` (file) and `image_url` are sent, the file upload takes priority.

---

### n8n Example 4: List Posts by Category

Fetches the latest 20 posts in the `tech` category.

#### HTTP Request Node Configuration

| Setting | Value |
|---|---|
| **Method** | GET |
| **URL** | `https://your-domain.com/api/posts?category=tech&per_page=20&sort=latest` |
| **Authentication** | Header Auth |
| **Header Name** | `Authorization` |
| **Header Value** | `Bearer YOUR_API_TOKEN` |

#### n8n JSON Import

```json
{
  "name": "List Posts by Category",
  "nodes": [
    {
      "parameters": {
        "method": "GET",
        "url": "https://your-domain.com/api/posts?category=tech&per_page=20&sort=latest",
        "authentication": "genericCredentialType",
        "genericAuthType": "httpHeaderAuth",
        "sendHeaders": true,
        "headerParameters": {
          "parameters": [
            {"name": "Authorization", "value": "Bearer YOUR_API_TOKEN"}
          ]
        },
        "options": {}
      },
      "name": "HTTP Request",
      "type": "n8n-nodes-base.httpRequest",
      "position": [250, 300]
    }
  ]
}
```

---

### n8n Example 5: Paginate Through All Posts

This example shows how to use the `loop` approach in n8n to fetch all posts across multiple pages. The strategy:

1. Make the first request with `?per_page=100&page=1`.
2. Check if `meta.current_page < meta.last_page`.
3. If so, loop and increment `page`.

#### n8n JSON Import

```json
{
  "name": "Paginate All Posts",
  "nodes": [
    {
      "parameters": {
        "method": "GET",
        "url": "https://your-domain.com/api/posts?per_page=100&page={{ $json.page || 1 }}",
        "authentication": "genericCredentialType",
        "genericAuthType": "httpHeaderAuth",
        "sendHeaders": true,
        "headerParameters": {
          "parameters": [
            {"name": "Authorization", "value": "Bearer YOUR_API_TOKEN"}
          ]
        },
        "options": {}
      },
      "name": "HTTP Request",
      "type": "n8n-nodes-base.httpRequest",
      "position": [250, 300]
    }
  ]
}
```

Alternatively, use `?limit=1000` to pull up to 1000 posts in a single request without pagination.

---

### n8n Example 6: Delete a Post

Deletes a post by its UUID. Useful for cleanup workflows.

#### HTTP Request Node Configuration

| Setting | Value |
|---|---|
| **Method** | DELETE |
| **URL** | `https://your-domain.com/api/posts/{{ $json.uuid }}` |
| **Authentication** | Header Auth |
| **Header Name** | `Authorization` |
| **Header Value** | `Bearer YOUR_API_TOKEN` |

#### n8n JSON Import

```json
{
  "name": "Delete a Post",
  "nodes": [
    {
      "parameters": {
        "method": "DELETE",
        "url": "https://your-domain.com/api/posts/={{ $json.uuid }}",
        "authentication": "genericCredentialType",
        "genericAuthType": "httpHeaderAuth",
        "sendHeaders": true,
        "headerParameters": {
          "parameters": [
            {"name": "Authorization", "value": "Bearer YOUR_API_TOKEN"}
          ]
        },
        "options": {}
      },
      "name": "HTTP Request",
      "type": "n8n-nodes-base.httpRequest",
      "position": [250, 300]
    }
  ]
}
```

---

## Rate Limiting

There is no built-in rate limiting on the API. If you need rate limiting, configure it at your reverse proxy (Nginx, Traefik, Caddy, etc.).

---

## Support

- **GitHub Issues:** [https://github.com/ivancarlosti/socialtokenizator/issues](https://github.com/ivancarlosti/socialtokenizator/issues)
- **Consulting:** [https://ivancarlos.me](https://ivancarlos.me)
