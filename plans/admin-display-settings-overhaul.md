# Admin Display Settings Overhaul — Implementation Plan

## Goal

Refactor several admin settings to improve per-user API token handling, author links,
per-context post metadata, timezone control, and post description display on list pages
and feeds.

---

## 1. API token moves back to the RestAPI tab

**Current state:** the Users tab renders every user's API token with generate/regenerate/
revoke/copy controls. The RestAPI tab only shows a note pointing to the Users tab plus the
IP allowlist.

**Target state:**

- The RestAPI tab shows **only the current logged-in user's own token** (masked), with
  Copy / Generate / Regenerate / Revoke buttons.
- The Users tab no longer shows API tokens at all.

### Changes

- [`app/Http/Controllers/Admin/SettingsController.php`](app/Http/Controllers/Admin/SettingsController.php)
  - In `edit()`, resolve the current user from `session('admin_user_id')` and pass it as
    `currentUser` (nullable) to the view.
  - In `update()`, replace the `users.*.api_token_action` handling with a single
    `api_token_action` field applied to the current user.
  - Validation: add `'api_token_action' => ['nullable', 'string', 'in:generate,regenerate,revoke']`
    and remove `'users.*.api_token_action'`.
- [`resources/views/admin/settings.blade.php`](resources/views/admin/settings.blade.php)
  - Remove the token block (lines 471–496) from the Users tab.
  - Add the current-user token block to the RestAPI tab, guarded by `@if($currentUser)`.
  - Update the RestAPI note so it describes self-service token management instead of
    pointing to the Users tab.
- [`app/Models/User.php`](app/Models/User.php)
  - Existing `generateApiToken()` / `revokeApiToken()` helpers can be reused, or the
    controller can keep using `Str::random(64)` as today.

---

## 2. Optional link on user name (opens in new window)

- Add nullable `url` column to `users` via a new migration
  (e.g. `database/migrations/2026_08_29_000004_add_url_to_users_table.php`).
- [`app/Models/User.php`](app/Models/User.php): add `url` to `$fillable`.
- Users tab ([`settings.blade.php`](resources/views/admin/settings.blade.php)): add an
  optional "Link" input for each existing user and for the new-user form.
- [`SettingsController.php`](app/Http/Controllers/Admin/SettingsController.php):
  - Validation: `'users.*.url' => ['nullable', 'url:http,https', 'max:2048']`,
    `'new_user_url' => ['nullable', 'url:http,https', 'max:2048']`.
  - `syncUsers()`: persist `url` (empty string becomes `null`); include `url` when creating
    the new user.
- Author name rendering — wrap `displayName()` in an `<a target="_blank" rel="noopener noreferrer">`
  when `$author->url` is non-empty, in:
  - [`resources/views/home.blade.php`](resources/views/home.blade.php)
  - [`resources/views/search.blade.php`](resources/views/search.blade.php)
  - [`resources/views/image/show.blade.php`](resources/views/image/show.blade.php)

---

## 3. Split author / published / updated into two sections

**Current state:** three global booleans `show_post_author`, `show_post_published`,
`show_post_updated` drive both the single post page and the home feed.

**Target state:** two independent groups in the Appearance tab:

| Context | Setting keys |
|---|---|
| Single post page | `show_post_author`, `show_post_published`, `show_post_updated` (existing keys retained for backward compatibility) |
| List pages (front + search) | `show_post_author_in_list`, `show_post_published_in_list`, `show_post_updated_in_list` |

### Changes

- [`settings.blade.php`](resources/views/admin/settings.blade.php): replace the single
  "Show post author & date/time" block with two labeled subsections.
- [`SettingsController.php`](app/Http/Controllers/Admin/SettingsController.php):
  - `edit()`: pass `showPostAuthorInList`, `showPostPublishedInList`,
    `showPostUpdatedInList` (bool).
  - `update()`: validate the three `*_in_list` booleans and persist using the existing
    checkbox pattern.
- [`app/Providers/AppServiceProvider.php`](app/Providers/AppServiceProvider.php): add the
  three list booleans to the view composer.
- [`resources/views/home.blade.php`](resources/views/home.blade.php): switch to the
  `*InList` variables.
- [`resources/views/search.blade.php`](resources/views/search.blade.php): add the
  author/published/updated block using the `*InList` variables (search currently has no
  author/date block at all).
- [`resources/views/image/show.blade.php`](resources/views/image/show.blade.php): keeps
  using the single-page variables (names unchanged).

---

## 4. Website timezone setting

- New setting key: `site_timezone` (string), default `config('app.timezone')` (`UTC`).
- [`settings.blade.php`](resources/views/admin/settings.blade.php): add a timezone
  `<select>` on the Appearance tab populated with `timezone_identifiers_list()`.
- [`SettingsController.php`](app/Http/Controllers/Admin/SettingsController.php):
  - `edit()`: pass `siteTimezone` and the timezone list.
  - `update()`: validate `in:` against `timezone_identifiers_list()`; persist or forget.
- [`app/Providers/AppServiceProvider.php`](app/Providers/AppServiceProvider.php): pass a
  sanitized `siteTimezone` (valid timezone or `UTC`) to all views via the existing composer.
- Timezone-aware display (human-readable dates only; machine-readable feed/meta timestamps
  stay in UTC/ISO with offsets):
  - [`resources/views/home.blade.php`](resources/views/home.blade.php): use
    `$img->created_at->setTimezone($siteTimezone)->format('Y-m-d H:i')` (same for updated).
  - [`resources/views/image/show.blade.php`](resources/views/image/show.blade.php): same
    conversion for published/updated display lines.
  - `publishedIso`/`modifiedIso` for `<meta>` tags remain `toIso8601String()` (UTC+offset),
    which is correct for `article:published_time` / `article:modified_time`.

---

## 5. Post description on list pages (front + search)

New settings:

| Key | Type | Default |
|---|---|---|
| `show_post_description_in_list` | bool (`'1'` / absent) | absent (off, preserving current behavior) |
| `post_description_in_list_mode` | `excerpt` \| `full` | `excerpt` |
| `post_description_in_list_length` | int (1–2000) | `300` |

### Changes

- [`settings.blade.php`](resources/views/admin/settings.blade.php): add a "Post description
  in lists" section (enable checkbox, mode select, length input) on the Appearance tab.
- [`SettingsController.php`](app/Http/Controllers/Admin/SettingsController.php): pass and
  persist the three settings.
- [`app/Providers/AppServiceProvider.php`](app/Providers/AppServiceProvider.php): expose
  `showPostDescriptionInList`, `postDescriptionInListMode`, `postDescriptionInListLength`.
- [`resources/views/home.blade.php`](resources/views/home.blade.php) and
  [`resources/views/search.blade.php`](resources/views/search.blade.php): render
  `$img->getDescription($currentLocale)` when enabled; excerpt uses
  `\Illuminate\Support\Str::limit($desc, $length)`, full renders the complete text.

---

## 6. Post description on feeds (Atom / RSS / JSON)

New settings (separate from list pages so each context is independently configurable):

| Key | Type | Default |
|---|---|---|
| `show_post_description_in_feed` | bool | `'1'` (on, preserving current behavior) |
| `post_description_in_feed_mode` | `excerpt` \| `full` | `full` (preserving current behavior) |
| `post_description_in_feed_length` | int (1–2000) | `300` |

### Changes

- [`settings.blade.php`](resources/views/admin/settings.blade.php): add a "Post description
  in feed" section (enable checkbox, mode select, length input) on the Appearance tab.
- [`SettingsController.php`](app/Http/Controllers/Admin/SettingsController.php): pass and
  persist the three settings.
- [`app/Http/Controllers/FeedController.php`](app/Http/Controllers/FeedController.php):
  read the settings (via `Setting::get`) in `buildAtomXml`, `buildRssXml`, and
  `buildJsonFeed`:
  - **disabled**: omit `summary`/`content` (Atom), `description` (RSS), and
    `summary`/`content_html` (JSON). Title/link/id/updated remain.
  - **excerpt**: use `Str::limit($description, $length)` for the rendered text.
  - **full**: current behavior (full description in content, short summary where applicable).

---

## 7. Remove author / dates from share buttons

- [`resources/views/image/show.blade.php`](resources/views/image/show.blade.php):
  remove the `$shareMeta` block and set `$shareText = $shortDesc`. The share intent text
  (X, Facebook, LinkedIn) and copy-link will contain only the short description.
  - The `post_author`, `post_published`, `post_updated` display block on the page remains
    unchanged.
  - `<meta name="author">` and `article:published_time` / `article:modified_time` tags are
    kept; only the share intent text changes.

---

## 8. Translation strings

Add/update keys in [`lang/en_US/messages.php`](lang/en_US/messages.php),
[`lang/pt_BR/messages.php`](lang/pt_BR/messages.php), and
[`lang/es_MX/messages.php`](lang/es_MX/messages.php). New/updated keys include:

- RestAPI self-service token note + heading (rework `settings_api_token_per_user_note`,
  add per-user token labels if needed).
- Users tab: `settings_users_link` ("Link (optional)") and help text; reword
  `settings_users_help` to drop the token mention.
- Split metadata labels:
  - `settings_post_meta_single` (section title, e.g. "Single post page")
  - `settings_post_meta_list` (section title, e.g. "Pages with many posts (front page & search)")
  - list variants: `settings_show_post_author_in_list`,
    `settings_show_post_published_in_list`, `settings_show_post_updated_in_list` (+ help).
  - update existing single-page help texts to say "on the single post page".
- Timezone: `settings_site_timezone`, `settings_site_timezone_help`.
- List description: `settings_post_description_in_list`,
  `settings_post_description_mode`, `settings_post_description_mode_excerpt`,
  `settings_post_description_mode_full`, `settings_post_description_length`, + help.
- Feed description: `settings_post_description_in_feed` and the same mode/length labels.

---

## Files to modify

| File | Change |
|---|---|
| [`database/migrations/..._add_url_to_users_table.php`](database/migrations) | New migration: nullable `url` on `users` |
| [`app/Models/User.php`](app/Models/User.php) | Add `url` to fillable |
| [`app/Http/Controllers/Admin/SettingsController.php`](app/Http/Controllers/Admin/SettingsController.php) | Current-user token, user link, split/list/description/timezone settings |
| [`app/Providers/AppServiceProvider.php`](app/Providers/AppServiceProvider.php) | Expose list booleans, description vars, siteTimezone |
| [`app/Http/Controllers/FeedController.php`](app/Http/Controllers/FeedController.php) | Feed description enable/mode/length |
| [`resources/views/admin/settings.blade.php`](resources/views/admin/settings.blade.php) | Move token, add link fields, split sections, timezone + description UI |
| [`resources/views/home.blade.php`](resources/views/home.blade.php) | List vars, author link, description, timezone dates |
| [`resources/views/search.blade.php`](resources/views/search.blade.php) | Add author/date block, description, author link, timezone dates |
| [`resources/views/image/show.blade.php`](resources/views/image/show.blade.php) | Author link, timezone dates, remove share meta |
| [`lang/en_US/messages.php`](lang/en_US/messages.php), [`lang/pt_BR/messages.php`](lang/pt_BR/messages.php), [`lang/es_MX/messages.php`](lang/es_MX/messages.php) | New/updated keys |
