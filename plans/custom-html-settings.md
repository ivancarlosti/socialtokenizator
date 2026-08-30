# Custom HTML Settings — Implementation Plan

## Goal

1. On Settings → Appearance, stack the two short-ID checkboxes vertically and give each its own description.
2. Add a new "HTML" settings tab with three textareas:
   - Head injection (Google Analytics, meta tags, etc.)
   - Inline CSS (rendered as a `<style>` block when filled)
   - Inline JavaScript (rendered as a `<script>` block when filled)

Injections apply everywhere, including admin pages (shared [`layouts/app.blade.php`](resources/views/layouts/app.blade.php)).

---

## Settings keys

| Key | Purpose | Render location |
|-----|---------|-----------------|
| `custom_head` | Raw HTML injected into `<head>` | Before `</head>`, after favicon links |
| `custom_css`  | Inline CSS | `<style>{content}</style>` in `<head>` |
| `custom_js`   | Inline JavaScript | `<script>{content}</script>` before `</body>` |

All are stored as plain text in the existing `settings` table (no migration needed).

---

## Step 1 — Fix short-ID checkboxes

File: [`resources/views/admin/settings.blade.php`](resources/views/admin/settings.blade.php)

Current block (Appearance tab, around lines 148–162) has the first label as `inline-flex`
with no `block`, so both checkboxes render on one line. Change both labels to
`inline-flex ... block` (or `flex`) so each starts a new line, and add an individual
help paragraph under each checkbox. Keep the existing general note
(`settings_short_id_help`) at the end of the group.

Target markup:

```blade
<label class="flex items-center gap-2 text-sm text-copy mt-3">
    <input type="hidden" name="short_id_uppercase" value="0">
    <input type="checkbox" name="short_id_uppercase" value="1" @checked($shortIdUppercase)>
    {{ __('messages.settings_short_id_uppercase') }}
</label>
<p class="mt-1 text-xs text-muted">{{ __('messages.settings_short_id_uppercase_help') }}</p>

<label class="flex items-center gap-2 text-sm text-copy mt-3">
    <input type="hidden" name="short_id_numbers" value="0">
    <input type="checkbox" name="short_id_numbers" value="1" @checked($shortIdNumbers)>
    {{ __('messages.settings_short_id_numbers') }}
</label>
<p class="mt-1 text-xs text-muted">{{ __('messages.settings_short_id_numbers_help') }}</p>

<p class="mt-1 text-xs text-muted">{{ __('messages.settings_short_id_help') }}</p>
```

---

## Step 2 — Add HTML tab

File: [`resources/views/admin/settings.blade.php`](resources/views/admin/settings.blade.php)

### 2a — Tab button

Add a new `<button>` in the tab navigation (after Web Standards, before Users):

```blade
<button type="button" class="tab-btn px-4 py-2 rounded-t text-sm font-medium transition-colors"
        data-tab="html" role="tab" aria-selected="false">
    {{ __('messages.settings_tab_html') }}
</button>
```

### 2b — Tab panel

Add a new panel after the Web Standards panel:

```blade
<div class="tab-panel hidden bg-card border border-card-border rounded p-5 space-y-6" data-tab="html" role="tabpanel">
    <div>
        <label class="block text-sm font-semibold text-copy mb-1">{{ __('messages.settings_custom_head') }}</label>
        <p class="text-xs text-muted mb-2">{{ __('messages.settings_custom_head_help') }}</p>
        <textarea name="custom_head" rows="8" maxlength="50000"
                  class="w-full bg-input border border-input-border rounded px-3 py-2 text-sm text-copy font-mono"
                  placeholder="<!-- Google Analytics, <meta> tags, etc. -->">{{ old('custom_head', $customHead) }}</textarea>
    </div>

    <div>
        <label class="block text-sm font-semibold text-copy mb-1">{{ __('messages.settings_custom_css') }}</label>
        <p class="text-xs text-muted mb-2">{{ __('messages.settings_custom_css_help') }}</p>
        <textarea name="custom_css" rows="8" maxlength="50000"
                  class="w-full bg-input border border-input-border rounded px-3 py-2 text-sm text-copy font-mono"
                  placeholder="body { ... }">{{ old('custom_css', $customCss) }}</textarea>
    </div>

    <div>
        <label class="block text-sm font-semibold text-copy mb-1">{{ __('messages.settings_custom_js') }}</label>
        <p class="text-xs text-muted mb-2">{{ __('messages.settings_custom_js_help') }}</p>
        <textarea name="custom_js" rows="8" maxlength="50000"
                  class="w-full bg-input border border-input-border rounded px-3 py-2 text-sm text-copy font-mono"
                  placeholder="console.log('hello');">{{ old('custom_js', $customJs) }}</textarea>
    </div>
</div>
```

The tab-switching JS already handles any `.tab-btn` / `.tab-panel` pair generically, so no JS changes are required.

---

## Step 3 — Controller

File: [`app/Http/Controllers/Admin/SettingsController.php`](app/Http/Controllers/Admin/SettingsController.php)

### 3a — `edit()`

Add to the view data array:

```php
'customHead' => Setting::get('custom_head', ''),
'customCss'  => Setting::get('custom_css', ''),
'customJs'   => Setting::get('custom_js', ''),
```

### 3b — `update()`

Add validation rules:

```php
'custom_head' => ['nullable', 'string', 'max:50000'],
'custom_css'  => ['nullable', 'string', 'max:50000'],
'custom_js'   => ['nullable', 'string', 'max:50000'],
```

Add persistence (same trim/store-or-forget pattern as the other text fields):

```php
$customHead = trim((string) ($request->input('custom_head', '')));
if ($customHead !== '') {
    Setting::put('custom_head', $customHead);
} else {
    Setting::forget('custom_head');
}
// ... same for custom_css and custom_js
```

`Setting::flushCache()` is already called at the end of `update()`, so the composer will see fresh values immediately.

---

## Step 4 — View composer

File: [`app/Providers/AppServiceProvider.php`](app/Providers/AppServiceProvider.php)

In the `View::composer('*', ...)` closure, read:

```php
$customHead = $this->safeSetting('custom_head');
$customCss  = $this->safeSetting('custom_css');
$customJs   = $this->safeSetting('custom_js');
```

And add to the `$view->with([...])` array:

```php
'customHead' => $customHead,
'customCss'  => $customCss,
'customJs'   => $customJs,
```

---

## Step 5 — Layout injection

File: [`resources/views/layouts/app.blade.php`](resources/views/layouts/app.blade.php)

### 5a — Head

In `<head>`, after the stylesheet link (before `</head>`):

```blade
@if($customHead)
    {!! $customHead !!}
@endif

@if($customCss)
    <style>
        {!! $customCss !!}
    </style>
@endif
```

### 5b — JavaScript

Before `</body>`, after the existing theme `<script>` block:

```blade
@if($customJs)
    <script>
        {!! $customJs !!}
    </script>
@endif
```

Raw `{!! !!}` output is intentional — this is admin-controlled HTML/CSS/JS and must not be escaped.

---

## Step 6 — Language strings

Add to all three files: [`lang/en_US/messages.php`](lang/en_US/messages.php), [`lang/pt_BR/messages.php`](lang/pt_BR/messages.php), [`lang/es_MX/messages.php`](lang/es_MX/messages.php).

### New keys

```php
// HTML tab
'settings_tab_html'            => 'HTML',
'settings_custom_head'         => 'Head injection',
'settings_custom_head_help'    => 'HTML injected inside the <head> of every page. Use it for Google Analytics, <meta> tags, or other tracking code.',
'settings_custom_css'          => 'Custom CSS',
'settings_custom_css_help'     => 'Inline CSS. When filled, a <style> block is added to every page.',
'settings_custom_js'           => 'Custom JavaScript',
'settings_custom_js_help'      => 'Inline JavaScript. When filled, a <script> block is added before the closing </body> tag.',

// Short ID per-option descriptions
'settings_short_id_uppercase_help' => 'Allows A–Z letters in newly generated short post IDs.',
'settings_short_id_numbers_help'   => 'Allows 0–9 digits in newly generated short post IDs.',
```

Suggested Portuguese and Spanish equivalents follow the same key names with
translated values (implementer to fill in during the code pass).

---

## Files to modify

| File | Change |
|------|--------|
| [`resources/views/admin/settings.blade.php`](resources/views/admin/settings.blade.php) | Fix checkbox layout + add HTML tab button and panel |
| [`app/Http/Controllers/Admin/SettingsController.php`](app/Http/Controllers/Admin/SettingsController.php) | Pass, validate, persist the three fields |
| [`app/Providers/AppServiceProvider.php`](app/Providers/AppServiceProvider.php) | Expose `customHead`, `customCss`, `customJs` to views |
| [`resources/views/layouts/app.blade.php`](resources/views/layouts/app.blade.php) | Render head CSS injection and body JS injection |
| [`lang/en_US/messages.php`](lang/en_US/messages.php) | Add new keys |
| [`lang/pt_BR/messages.php`](lang/pt_BR/messages.php) | Add new keys |
| [`lang/es_MX/messages.php`](lang/es_MX/messages.php) | Add new keys |

No new files and no database migration are required.
