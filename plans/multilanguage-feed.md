# Multilanguage Feed: Atom, RSS, JSON

## Overview

Implement three feed formats (`/feed` → Atom, `/rss` → RSS 2.0, `/json` → JSON Feed v1.1), each filterable by language via a `?lang=` query parameter. The header gets `<link rel="alternate">` tags for auto-discovery, and the feed button links to the Atom feed with the current locale.

## Architecture

```
Browser visits /page
  → SetLocale Middleware (session locale or default)
  → AppServiceProvider View Composer
    → computes feedAtomUrl, feedRssUrl, feedJsonUrl with lang param
  → layout/app.blade.php
    → <link rel="alternate"> tags in <head>
    → Feed button in nav → /feed?lang=pt_BR

GET /feed?lang=pt_BR&category=...&tag=...  → FeedController::atom()
GET /rss?lang=pt_BR&category=...&tag=...   → FeedController::rss()
GET /json?lang=pt_BR&category=...&tag=...  → FeedController::json()

All three → resolveFeedLocale() → lang param OR default_locale setting
  → Filter Images: WHERE headline_XX != '' OR description_XX != ''
  → Build format-specific output using locale content
```

## Files to Modify

### 1. routes/web.php
Replace single `/feed` route with three named routes:
- `GET /feed` → `FeedController::atom` → name `feed.atom`
- `GET /rss` → `FeedController::rss` → name `feed.rss`
- `GET /json` → `FeedController::json` → name `feed.json`

### 2. app/Http/Controllers/FeedController.php
Major refactor:

**a) Extract `resolveFeedLocale(Request): string`**
- Read `?lang=` query param
- Validate against `Locales::isSupported()`
- Fallback to `Setting::get('default_locale')` → config fallback
- Returns locale (e.g. `pt_BR`)

**b) Extract `buildFeedQuery(Request, string $locale)`**
- Build column names: `headline_XX`, `description_XX` (replace `-` with `_`)
- Filter: `WHERE (headline_XX IS NOT NULL AND headline_XX != '') OR (description_XX IS NOT NULL AND description_XX != '')`
- Apply category/tag filters, limit
- Returns filtered Image collection

**c) Three public methods: `atom()`, `rss()`, `json()`**
- Each calls `resolveFeedLocale` + `buildFeedQuery`
- Each builds format-specific output

**d) `buildAtomXml()` — existing, refactored**
- Accept `$locale` instead of `$defaultLocale`
- Use `$locale` for `xml:lang`, entry titles, descriptions, category names

**e) `buildRssXml()` — new RSS 2.0 builder**
- `<rss version="2.0">` → `<channel>`
- `<title>`, `<link>`, `<description>`, `<language>`, `<lastBuildDate>`
- `<item>` entries with `<title>`, `<link>`, `<guid>`, `<pubDate>`, `<description>` (CDATA)
- Content-Type: `application/rss+xml; charset=utf-8`

**f) `buildJsonFeed()` — new JSON Feed v1.1 builder**
- JSON: `version`, `title`, `home_page_url`, `feed_url`, `language`, `items[]`
- Items: `id`, `url`, `title`, `content_html`, `summary`, `date_published`, `tags`
- Content-Type: `application/feed+json; charset=utf-8`

### 3. app/Providers/AppServiceProvider.php
Replace single `$feedUrl` with three format-specific URLs:

```php
$feedLang = $locale; // current page locale
$feedQueryParams = [];

// Preserve category/tag context from current page
$routeName = request()->route()?->getName();
if ($routeName === 'home') {
    $cat = request()->query('category');
    $tag = request()->query('tag');
    if ($cat && is_string($cat) && $cat !== '') {
        $feedQueryParams['category'] = $cat;
    } elseif ($tag && is_string($tag) && $tag !== '') {
        $feedQueryParams['tag'] = $tag;
    }
}

$feedAtomUrl = route('feed.atom', array_merge(['lang' => $feedLang], $feedQueryParams));
$feedRssUrl  = route('feed.rss',  array_merge(['lang' => $feedLang], $feedQueryParams));
$feedJsonUrl = route('feed.json', array_merge(['lang' => $feedLang], $feedQueryParams));
```

Share to views: `feedUrl` (backward compat, = `$feedAtomUrl`), `feedAtomUrl`, `feedRssUrl`, `feedJsonUrl`.

### 4. resources/views/layouts/app.blade.php
Add three `<link rel="alternate">` tags in `<head>` (after canonical):

```html
<link rel="alternate" type="application/atom+xml"  href="{{ $feedAtomUrl }}" title="{{ $siteTitle }} — Atom">
<link rel="alternate" type="application/rss+xml"   href="{{ $feedRssUrl }}"  title="{{ $siteTitle }} — RSS">
<link rel="alternate" type="application/feed+json" href="{{ $feedJsonUrl }}" title="{{ $siteTitle }} — JSON Feed">
```

Feed button (line 87) already uses `$feedUrl` which will carry the localized Atom URL — no change needed.

### 5. Language files
Add to `lang/en-US/messages.php`, `lang/pt_BR/messages.php`, `lang/es_MX/messages.php`:

```php
'feed_rss'  => 'RSS feed',     // en-US
'feed_rss'  => 'Feed RSS',     // pt_BR
'feed_rss'  => 'Feed RSS',     // es_MX
'feed_json' => 'JSON feed',    // en-US
'feed_json' => 'Feed JSON',    // pt_BR
'feed_json' => 'Feed JSON',    // es_MX
```

## Behavior

| Endpoint | Format | Example |
|----------|--------|---------|
| `/feed?lang=pt_BR` | Atom XML | `xml:lang="pt-BR"`, only posts with pt_BR content |
| `/rss?lang=en_US` | RSS 2.0 XML | `<language>en-us</language>`, only posts with en_US content |
| `/json?lang=es_MX` | JSON Feed | `"language": "es-MX"`, only posts with es_MX content |

- **No `lang` param**: uses `default_locale` setting (or `pt_BR` from config)
- **Header button**: links to `/feed?lang={currentLocale}` (Atom)
- **`<link rel="alternate">`**: all 3 formats with current locale
