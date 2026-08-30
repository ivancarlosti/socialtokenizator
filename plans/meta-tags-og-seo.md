# OpenGraph / Meta Tags / SEO Fix Plan

## Scope

Fix all reported issues in the shared layout and the post detail page:

- apple-touch-icon (180x180) + retina variants
- og:image:alt
- og:locale (+ alternates) following the default locale setting
- twitter:site (site level) and twitter:creator (per author, when available)
- JSON-LD structured data (WebSite/SearchAction on homepage, ImageObject on posts)
- theme-color following the dark/light default theme
- 32x32 PNG favicon + SVG favicon
- web app manifest (`/site.webmanifest`)

## Favicon generation (confirmed approach)

Single existing favicon upload in Settings → Appearance is processed as follows:

| Upload MIME        | Stored variants                                                          |
| ------------------ | ------------------------------------------------------------------------ |
| image/png, jpeg, webp, gif, avif | original + resized PNGs: 32x32, 180x180 (apple-touch-icon), 192x192, 512x512 |
| image/svg+xml      | original kept as `favicon.svg` (no raster variants; apple-touch-icon omitted) |
| image/x-icon (ico) | original kept as-is (no resizing; GD has no ICO decoder)                 |

Variant R2 keys are deterministic and mirrored to settings:

- `site_favicon_key` (original / fallback)
- `site_favicon_svg_key`
- `site_favicon_32_key`
- `site_favicon_180_key`
- `site_favicon_192_key`
- `site_favicon_512_key`

## New settings keys

- `twitter_site` — site X/Twitter handle (rendered as `@handle`)
- `theme_color_light` — default `#f9fafb`
- `theme_color_dark` — default `#111827`

## Target `<head>` output (shared layout)

```html
<!-- Favicons -->
<link rel="icon" type="image/svg+xml" href="https://…/branding/favicon/favicon.svg">
<link rel="icon" type="image/png" sizes="32x32" href="https://…/branding/favicon/favicon-32x32.png">
<link rel="shortcut icon" href="https://…/branding/favicon/{uuid}.png">
<link rel="apple-touch-icon" sizes="180x180" href="https://…/branding/favicon/apple-touch-icon.png">

<!-- Web app manifest -->
<link rel="manifest" href="https://domain/site.webmanifest">

<!-- Theme color -->
<meta name="theme-color" content="#111827">

<!-- OpenGraph locale (default locale from settings) -->
<meta property="og:locale" content="pt_BR">
<meta property="og:locale:alternate" content="en_US">
<meta property="og:locale:alternate" content="es_MX">

<!-- Twitter site -->
<meta name="twitter:site" content="@yourusername">
```

Each conditional tag is only emitted when its setting/variant exists.

## Default meta branch additions (layouts/app.blade.php)

Add after `og:image`:

```html
<meta property="og:image:alt" content="{{ $defaultTitle }}">
```

## Post page meta additions (image/show.blade.php)

```html
<meta property="og:image:alt" content="{{ $ogHeadline ? $siteTitle . ' — ' . $ogHeadline : $siteTitle }}">
@if($image->author?->twitterHandle())
<meta name="twitter:creator" content="{{ $image->author->twitterHandle() }}">
@endif
```

## JSON-LD

Homepage (`home.blade.php`):

```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "WebSite",
  "name": "{site title}",
  "url": "https://domain/",
  "potentialAction": {
    "@type": "SearchAction",
    "target": {
      "@type": "EntryPoint",
      "urlTemplate": "https://domain/search?q={search_term_string}"
    },
    "query-input": "required name=search_term_string"
  }
}
</script>
```

Post page (`image/show.blade.php`) — ImageObject with `contentUrl`, `url`, `name`, `description`, `datePublished`, `dateModified`, `author` (Person), `publisher` (Organization).

## Theme color toggle behavior

Server renders the meta for the `default_theme` setting. The existing theme-toggle JS updates `meta[name="theme-color"]` to the light/dark values when the user toggles (values injected via `@json`).

## Files touched

- `database/migrations/*_add_twitter_username_to_users_table.php` (new)
- `app/Models/User.php`
- `app/Support/FaviconProcessor.php` (new)
- `app/Http/Controllers/Admin/SettingsController.php`
- `app/Http/Controllers/WebStandardsController.php`
- `app/Providers/AppServiceProvider.php`
- `routes/web.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/image/show.blade.php`
- `resources/views/home.blade.php`
- `resources/views/admin/settings.blade.php`
- `lang/en_US/messages.php`, `lang/pt_BR/messages.php`, `lang/es_MX/messages.php`
