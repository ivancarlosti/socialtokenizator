# socialtokenizator

A self-hosted, single-container image-sharing web app:

- Public, anonymous browsing — every image gets a stable, shareable UUID URL.
- Private uploads behind a pluggable auth layer (`none` / `account` / `keycloak`).
- Uploads accept **JPG, PNG, WebP, GIF, and AVIF** images (max 10 MB).
- Object storage on **Cloudflare R2** (S3-compatible).
- MySQL for metadata, tags, categories, and source links.
- Server-rendered Open Graph + Twitter Card tags so links unfurl perfectly on X and Facebook.
- Three-language UI (EN_US, es_MX, pt_BR) with AI-powered translation for post descriptions.
- Dark and light theme with auto-detection and manual toggle.
- Designed to live behind a reverse proxy (Nginx, Traefik, Caddy…).

The Docker image is published from this repo's GitHub Actions to:

```
ghcr.io/ivancarlosti/socialtokenizator:latest
```

`docker compose` **pulls** that image — it does not build it.

---

## Table of contents

1. [Architecture](#architecture)
2. [Quick start](#quick-start)
3. [Configuration reference (`.env`)](#configuration-reference-env)
4. [Cloudflare R2 setup](#cloudflare-r2-setup)
5. [Authentication modes](#authentication-modes)
   - [`none`](#auth_methodnone)
   - [`account` + reCAPTCHA](#auth_methodaccount)
   - [`keycloak` (OIDC SSO)](#auth_methodkeycloak)
6. [AI Translation](#ai-translation)
7. [AI Generation](#ai-generation)
8. [Post URL prefix](#post-url-prefix)
9. [Categories](#categories)
10. [Admin settings](#admin-settings)
11. [REST API](#rest-api)
12. [Dark / light mode](#dark--light-mode)
13. [Reverse-proxy examples](#reverse-proxy-examples)
14. [Operating the app](#operating-the-app)
15. [Troubleshooting](#troubleshooting)
16. [Repository layout](#repository-layout)

---

## Architecture

```
                        ┌────────────────────────┐
   browser  ─── HTTPS ──┤   reverse proxy (you)  │── HTTP ──┐
                        └────────────────────────┘          │
                                                            ▼
                                            ┌──────────────────────────────┐
                                            │  app container (this image)  │
                                            │  nginx + php-fpm + Laravel   │
                                            └──────────────┬───────────────┘
                                                           │ MySQL (TCP)
                                                           ▼
                                            ┌──────────────────────────────┐
                                            │   external MySQL / MariaDB   │
                                            │        (you provide)         │
                                            └──────────────────────────────┘

                               Cloudflare R2 (object storage, public read URL)
                               ▲                                          ▲
                               └── PUTs from app on upload                └── GETs from browsers
```

- The app container exposes only port **80** internally; the host port (default `8767`) is set by `PORT`.
- Image bytes never live in the container or in MySQL — only the R2 object key + metadata.
- The compose file does **not** include a MySQL service — you point the app at an existing database via `DB_HOST`.

---

## Quick start

Prerequisites:

- Linux host with **Docker** + **Docker Compose v2**.
- A Cloudflare account (free tier is enough) with R2 enabled.
- A reverse proxy in front of the app for HTTPS (recommended).

Steps:

```bash
# 1. Get just the deploy folder
git clone https://github.com/ivancarlosti/socialtokenizator.git
cd socialtokenizator/docker

# 2. Configure
cp .env.example .env
$EDITOR .env       # fill in MySQL passwords, R2 keys, AUTH_METHOD, etc.

# 3. Generate APP_KEY (one-off)
docker run --rm ghcr.io/ivancarlosti/socialtokenizator:latest \
    php -r 'echo "base64:".base64_encode(random_bytes(32))."\n";'
# Paste the output into APP_KEY= in your .env

# 4. Pull and run
docker compose pull
docker compose up -d
```

Visit `http://<host>:8767` (or your proxied domain). On first boot the app waits for the database to be reachable, runs migrations, then starts serving.

---

## Configuration reference (`.env`)

All variables live in `/docker/.env`. The full template with comments is `/docker/.env.example`.

| Variable | Purpose |
|---|---|
| `APP_NAME` | Display name shown in the header and `<title>` |
| `APP_ENV` | `production` recommended |
| `APP_DEBUG` | `false` in production (otherwise stack traces leak) |
| `APP_KEY` | Laravel encryption key. Generate as shown in *Quick start* |
| `APP_URL` | Public URL; drives generated links and OG tags |
| `PORT` | Host port mapped to the app container's port 80 (default `8767`) |
| `DOMAIN` | Used by reverse-proxy snippets (Traefik labels, etc.) |
| `LOG_CHANNEL` / `LOG_LEVEL` | Logs go to container stderr by default |
| `SESSION_SECURE_COOKIE` | `true` when serving over HTTPS |
| `DB_HOST` … `DB_PASSWORD` | MySQL connection — point these at your external database server |
| `FILESYSTEM_DISK` | Always `r2` for production |
| `R2_ACCESS_KEY_ID` / `R2_SECRET_ACCESS_KEY` | R2 API token credentials |
| `R2_BUCKET` | Bucket name |
| `R2_ENDPOINT` | `https://<account-id>.r2.cloudflarestorage.com` |
| `R2_PUBLIC_URL` | Public read URL for the bucket (custom domain or `r2.dev`) |
| `R2_REGION` | Always `auto` for R2 |
| `AUTH_METHOD` | `none`, `account`, or `keycloak` — see [Authentication modes](#authentication-modes) |
| `ACCOUNT_LOGIN` / `ACCOUNT_PASSWORD` | Single admin credentials (only when `AUTH_METHOD=account`) |
| `RECAPTCHA_CLIENTID` / `RECAPTCHA_CLIENTSECRET` | Optional Google reCAPTCHA v2 keys |
| `KEYCLOAK_*` | OIDC config (only when `AUTH_METHOD=keycloak`) |
| `AI_API_KEY` | API key for AI translation (OpenAI-compatible; e.g. DeepSeek) |
| `AI_API_URL` | Base URL for AI chat completions API (e.g. `https://api.deepseek.com/v1`) |
| `AI_MODEL` | Model name for translation requests (e.g. `deepseek-v4-flash`) |

After changing `.env`, restart: `docker compose up -d` (Compose recreates the container if env changed).

---

## Cloudflare R2 setup

1. **Create an R2 bucket**
   - Cloudflare dashboard → **R2** → **Create bucket** → name it (e.g. `socialtokenizator`).
   - Copy the bucket name into `R2_BUCKET`.

2. **Find your account-level S3 endpoint**
   - In the R2 overview page, copy the *S3 API* URL — it looks like
     `https://<account-id>.r2.cloudflarestorage.com`.
   - Put that into `R2_ENDPOINT`.

3. **Create an API token**
   - **R2** → **Manage R2 API Tokens** → **Create API token**.
   - Permissions: **Object Read & Write**.
   - Scope: limit to the bucket you just created.
   - Save the token. Cloudflare shows the **Access Key ID** and **Secret Access Key** once — copy both into `R2_ACCESS_KEY_ID` and `R2_SECRET_ACCESS_KEY`.

4. **Make the bucket readable on the public web**

   Choose one of:

   - **Custom domain (recommended)**
     - Bucket → **Settings** → **Public access** → **Connect Domain** → enter a subdomain you control on Cloudflare (e.g. `images.socialtokenizator.example.com`). Cloudflare provisions DNS + TLS.
     - Set `R2_PUBLIC_URL=https://images.socialtokenizator.example.com`.
   - **`r2.dev` subdomain (quick, not for production use)**
     - Bucket → **Settings** → **Public access** → enable **Allow Access** for the `pub-…r2.dev` URL.
     - Set `R2_PUBLIC_URL=https://pub-xxxxxxxx.r2.dev`.

5. **CORS (only if you ever fetch images via JS / cross-origin)**

   Bucket → **Settings** → **CORS Policy** → add your app domain to `AllowedOrigins`. Default install does not require CORS because images are referenced as `<img src>`.

6. **Verify**
   - After restart, upload an image (admin mode) and visit the image's detail page. The `<img src>` should resolve to `R2_PUBLIC_URL/images/<uuid>.<ext>` and load successfully.

---

## Authentication modes

Set with `AUTH_METHOD` in `.env`. Restart the app after changing it.

### `AUTH_METHOD=none`

- Uploads are **disabled**. The `/admin/*` routes return `403`. There's no login UI.
- Use this for read-only public archives, or before you've configured an auth provider.

### `AUTH_METHOD=account`

A single admin login backed by env vars.

```
AUTH_METHOD=account
ACCOUNT_LOGIN=admin
ACCOUNT_PASSWORD=a-strong-password
```

`ACCOUNT_PASSWORD` accepts either plaintext (simplest) or a bcrypt hash. To generate a bcrypt hash locally:

```bash
docker run --rm ghcr.io/ivancarlosti/socialtokenizator:latest \
    php -r 'echo password_hash($argv[1], PASSWORD_BCRYPT)."\n";' 'a-strong-password'
```

Optionally protect the form with **Google reCAPTCHA v2** ("I'm not a robot"):

1. Go to <https://www.google.com/recaptcha/admin/create>.
2. **reCAPTCHA type** → *Challenge (v2)* → *"I'm not a robot" checkbox*.
3. **Domains** → add the public domain you serve from.
4. Copy the **Site Key** into `RECAPTCHA_CLIENTID` and the **Secret Key** into `RECAPTCHA_CLIENTSECRET`.
5. Restart the app. The login form now renders the widget and the server validates the response.

Login URL: `https://<your-domain>/auth/login`.

### `AUTH_METHOD=keycloak`

OIDC code flow against a Keycloak realm. Only one allow-listed email becomes admin.

1. In Keycloak, open your realm (or create one) → **Clients** → **Create client**.
   - Client type: **OpenID Connect**
   - Client ID: `socialtokenizator` (or any value — must match `KEYCLOAK_CLIENT_ID`)
   - Client authentication: **On** (we use the confidential client flow)
   - Valid redirect URIs: `https://socialtokenizator.example.com/auth/keycloak/callback`
     (must exactly match `KEYCLOAK_REDIRECT_URI`)
   - Web origins: `https://socialtokenizator.example.com`
2. **Credentials** tab → copy the client secret → `KEYCLOAK_CLIENT_SECRET`.
3. Fill in `.env`:
   ```
   AUTH_METHOD=keycloak
   KEYCLOAK_BASE_URL=https://sso.example.com
   KEYCLOAK_REALM=YOURSSORealm
   KEYCLOAK_CLIENT_ID=socialtokenizator
   KEYCLOAK_CLIENT_SECRET=...
   KEYCLOAK_REDIRECT_URI=https://socialtokenizator.example.com/auth/keycloak/callback
   KEYCLOAK_EMAIL_ACCOUNT=admin@example.com
   ```
4. Restart. Visiting `/admin` (or clicking *Login*) bounces to Keycloak and back. Only the user whose email matches `KEYCLOAK_EMAIL_ACCOUNT` is granted admin; everyone else gets `403`.
5. Logout uses RP-initiated logout (`/realms/<realm>/protocol/openid-connect/logout`).

---

## AI Translation

The app supports one-click AI translation for post descriptions in the admin panel. Each description field (English, Spanish, Brazilian Portuguese) has a **"Translate with AI"** link. When clicked, it sends the content from another already-filled description field to an AI provider and fills the target field with the translation.

### Setup

The translation feature uses the **OpenAI-compatible chat completions API**. Any provider that implements this standard works — OpenAI, DeepSeek, Groq, etc.

Add these variables to your `.env`:

```bash
# AI Translation (OpenAI-compatible API)
AI_API_KEY=sk-your-api-key-here
AI_API_URL=https://api.deepseek.com/v1
AI_MODEL=deepseek-v4-flash
```

### Example: DeepSeek

1. Sign up at <https://platform.deepseek.com>.
2. Generate an API key from the dashboard.
3. Use these values in your `.env`:

```
AI_API_KEY=sk-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
AI_API_URL=https://api.deepseek.com/v1
AI_MODEL=deepseek-v4-flash
```

4. Restart the app: `docker compose up -d`

The cheapest and fastest model for translation is `deepseek-v4-flash`. You can also use `deepseek-chat` for higher quality.

### How it works

1. In the upload or edit form, fill in one description field (e.g. English).
2. Next to an empty description field (e.g. Spanish), click **"Translate with AI"**.
3. The app calls `POST /admin/translate` which proxies the request to the AI API.
4. The AI returns only the translated text, which is placed into the target textarea.

The system prompt instructs the AI to preserve formatting, line breaks, and HTML tags during translation.

### Supported languages

| Locale code | AI prompt target |
|---|---|
| `en` | EN_US |
| `es` | es_MX |
| `pt_BR` | pt_BR |

---

## AI Generation

The app supports one-click AI post generation in the admin panel. On the **Upload** and **Edit** screens, a textarea lets you paste a press release or news article. Clicking **"Generate with AI"** sends the text to an AI provider, which returns headlines, descriptions (in all three languages), and tags — all populated into the form fields for review before publishing.

It uses the same **OpenAI-compatible chat completions API** as translation (same `AI_API_KEY`, `AI_API_URL`, and `AI_MODEL` env vars). No additional configuration is required.

### How it works

1. Paste a press release or news article into the **"Generate with AI"** textarea on the Upload or Edit screen.
2. Click **"Generate with AI"**.
3. The app calls `POST /admin/generate` which sends the text + your custom prompt to the AI API.
4. The AI returns a structured JSON response with headlines, descriptions (per language), and tags.
5. All form fields are populated — you can review, edit, and manually submit when ready.

### Customizing the prompt

The AI generation prompt is fully configurable in **Admin → Settings → AI Generate Prompt**. Leave it empty to use the built-in default (shown below). The placeholder `{{INPUT_TEXT}}` is replaced with the user's pasted text at runtime.

The default prompt is:

```
**Role and Objective**
You are an expert Social Media Manager and PR Copywriter. Your task is to analyze a provided Press Release or News article and transform it into a short, highly engaging post suitable for a broad audience.

**Tone and Style**
Your writing should be **enthusiastic and slightly sensationalist**. Use a captivating, hype-driven tone to grab the reader's attention immediately. Make the news sound exciting, groundbreaking, and highly relevant, while still keeping the core message of the original text.

**Content Requirements**
For the Press Release or News article provided, you must generate:
1. **Title / Headline:** A catchy, click-worthy headline.
2. **Post Body:** 1 to 3 short paragraphs summarizing the news and highlighting the most exciting elements.
3. **Tags:** 3 to 8 relevant tags (lowercase, short phrases) for categorizing the post.

**Language Requirements**
You must provide the complete output in all three of the following languages:
- "en-US" — English (US)
- "pt_BR" — Portuguese (Brazil)
- "es_MX" — Spanish (Mexico)

**Strict Privacy Constraints**
- **NO CONTACT DATA:** You must strictly filter out and completely omit any personal data, email addresses, phone numbers, or contact information related to the PR contact, the author, or the PR company that shared the news.
- Keep the focus solely on the product, event, or announcement itself.

**CRITICAL OUTPUT FORMAT**
You MUST respond with ONLY a valid JSON object. No markdown, no code fences, no explanations. The JSON structure must be exactly:

{
    "headlines": {
        "en-US": "English headline here",
        "pt_BR": "Portuguese headline here",
        "es_MX": "Spanish headline here"
    },
    "descriptions": {
        "en-US": "English description paragraphs here",
        "pt_BR": "Portuguese description paragraphs here",
        "es_MX": "Spanish description paragraphs here"
    },
    "tags": "tag1, tag2, tag3, tag4"
}

---
**Input Data:**
Here is the Press Release/News to process:
{{INPUT_TEXT}}
```

> **Tip:** To customize, copy the prompt above, paste it into **Admin → Settings → AI Generate Prompt**, modify as needed, and save. Keep `{{INPUT_TEXT}}` as the placeholder — it will be replaced with the user's pasted text.

---

## Post URL prefix

The default URL pattern for post detail pages is `/p/{uuid}`. You can change the `p` prefix in **Admin → Settings → Post URL prefix**. For example, setting it to `post` changes URLs to `/post/{uuid}`.

The old `/image/{uuid}` URLs automatically redirect (301) to the new pattern.

> **Note:** Changing the prefix requires clearing the route cache. Restart the container after saving: `docker compose restart`.

---

## Categories

Categories are managed in **Admin → Categories**. Each category has:

- **Handle** — a unique, lowercase identifier shared across all languages (e.g. `gaming-market`). Used in URLs for filtering.
- **Localized names** — one display name per supported language.

Categories appear as filter chips at the top of the homepage. Clicking a category filters the feed to show only images in that category. The "All" chip resets the filter.

### Adding a category

In **Admin → Categories**, fill in the handle and at least one localized name, then click **Add category**. The handle must be unique and contain only lowercase letters, numbers, hyphens, and underscores.

### Editing and deleting

Each existing category shows an inline edit form. You can change the handle or localized names and click **Save**. Deleting a category removes it from all associated images.

The image count next to each category shows how many images belong to it.

---

## Admin settings

All configurable options are in **Admin → Settings**. Changes take effect immediately after saving (except the post URL prefix — see [Post URL prefix](#post-url-prefix)).

| Setting | Description |
|---|---|
| **Site logo** | Replaces the header text. Accepts PNG, SVG, WEBP, JPG, GIF — max 2 MB. |
| **Favicon** | Browser tab icon. PNG, ICO, SVG, WEBP — max 512 KB. |
| **Default language** | Sets the default locale for visitors. Supports EN_US, es_MX, and pt_BR. |
| **Posts per page** | Number of images shown per page in the feed (1–100). |
| **Post URL prefix** | URL segment for post detail pages (default: `p`). Changing to `post` makes URLs `/post/{uuid}`. Requires container restart. |
| **Hide title section** | When checked, the site title and subtitle are hidden at the top of the homepage. |
| **Hide "Filter by category" label** | When checked, the "Filter by category" text above the category chips is hidden; the category chips remain visible and functional. |
| **Site title & subtitle (per language)** | Customizable heading and tagline for each supported language. Falls back to `APP_NAME` if empty. |
| **Footer HTML** | Custom HTML or text displayed on the right side of the footer. Accepts HTML tags (max 10 000 characters). |
| **Footer links** | Up to 3 labeled links displayed in the footer. Leave both label and URL empty to remove a link. |
| **AI Generate Prompt** | Customizable system prompt for AI post generation. Uses `{{INPUT_TEXT}}` as placeholder. Leave empty to use the built-in default. See [AI Generation](#ai-generation) for the default prompt text. |

---

## REST API

SocialTokenizator provides a REST API for programmatic post creation, listing, and deletion with image uploads. It uses Bearer token authentication — the token is generated in **Admin → Settings**.

Full documentation: [**API.md**](API.md) — includes endpoint specs, request/response examples, and n8n workflow configurations.

Quick overview:

| Endpoint | Method | Description |
|---|---|---|
| `/api/posts` | `POST` | Create a post (multipart: image + optional metadata) |
| `/api/posts` | `GET` | List posts with filters, search, and pagination |
| `/api/posts/{uuid}` | `GET` | Get a single post by UUID |
| `/api/posts/{uuid}` | `DELETE` | Delete a post and its image |

Authentication: `Authorization: Bearer <token>` header.

---

## Dark / light mode

The app ships with a dark/light theme toggle in the navigation bar (sun/moon icon). By default it follows the browser's `prefers-color-scheme` setting. The user's choice is persisted in `localStorage` and survives page reloads.

The theme is implemented via CSS custom properties that switch between two color palettes — one for light mode (`:root`) and one for dark mode (`.dark` class). All UI components reference these variables, so both themes work consistently across every page.

---

## Reverse-proxy examples

The app trusts `X-Forwarded-*` headers from any upstream (it has no exposed surface beyond the compose host port), so most proxies "just work". Two starter snippets:

### Nginx (host system)

```nginx
server {
    listen 443 ssl http2;
    server_name socialtokenizator.example.com;

    # ssl_certificate     /etc/letsencrypt/live/.../fullchain.pem;
    # ssl_certificate_key /etc/letsencrypt/live/.../privkey.pem;

    client_max_body_size 12m;

    location / {
        proxy_pass         http://127.0.0.1:8767;
        proxy_http_version 1.1;
        proxy_set_header   Host              $host;
        proxy_set_header   X-Real-IP         $remote_addr;
        proxy_set_header   X-Forwarded-For   $proxy_add_x_forwarded_for;
        proxy_set_header   X-Forwarded-Proto $scheme;
        proxy_set_header   X-Forwarded-Host  $host;
        proxy_set_header   X-Forwarded-Port  $server_port;
    }
}
```

### Traefik (Docker labels)

Uncomment the `labels:` block at the bottom of `/docker/docker-compose.yml` and join the app container to your existing `proxy` network. Sample:

```yaml
labels:
  - "traefik.enable=true"
  - "traefik.http.routers.socialtokenizator.rule=Host(`${DOMAIN}`)"
  - "traefik.http.routers.socialtokenizator.entrypoints=websecure"
  - "traefik.http.routers.socialtokenizator.tls.certresolver=le"
  - "traefik.http.services.socialtokenizator.loadbalancer.server.port=80"
```

---

## Operating the app

### Updating

```bash
cd /path/to/socialtokenizator/docker
docker compose pull
docker compose up -d
```

Migrations run automatically on container start.

### Logs

```bash
docker compose logs -f socialtokenizator
```

PHP-FPM, nginx, and the entrypoint script all stream to the container's stdout/stderr.

### Backups

- **MySQL** — back up your external database using `mysqldump` or your preferred backup tool.
- **Images** — they live in R2; use Cloudflare R2's lifecycle / replication tools, or `rclone` against the S3 endpoint, depending on your retention plan.

### Verifying social-share previews

After publishing an image:

- **Facebook**: <https://developers.facebook.com/tools/debug/> → enter the image page URL → *Scrape Again*.
- **X (Twitter)**: <https://cards-dev.twitter.com/validator>.

Both tools should show the image and description from your image detail page.

---

## Troubleshooting

| Symptom | Likely cause / fix |
|---|---|
| `502 Bad Gateway` on first boot | The app is still waiting for the database. `docker compose logs socialtokenizator` should show `Waiting for MySQL…`. Wait ~20 s. |
| `SQLSTATE[HY000] [2002]` | Database never became reachable. Check `DB_HOST`, `DB_PORT`, `DB_USERNAME`, and `DB_PASSWORD` in your `.env`. |
| Images return `403` from R2 | Bucket public access not configured. Either enable the `r2.dev` URL or attach a custom domain. Re-check `R2_PUBLIC_URL`. |
| Images upload but display broken | `R2_PUBLIC_URL` mismatch with where the bucket is actually served. Open the image in a new tab — the URL should load directly. |
| OG/Twitter previews stale | Social platforms cache aggressively. Use the *Sharing Debugger* / *Card Validator* and click "scrape again". |
| Login at `/auth/login` returns 404 | `AUTH_METHOD` is not set to `account`, or the container hasn't been restarted after editing `.env`. |
| Keycloak callback returns `Invalid redirect_uri` | The URI configured in the Keycloak client must **exactly** match `KEYCLOAK_REDIRECT_URI` (scheme + host + path). |
| `419 Page Expired` on form posts | Likely a cookie / proxy issue. Make sure `APP_URL` matches the public scheme/host and `SESSION_SECURE_COOKIE` is `true` only over HTTPS. |

---

## Repository layout

```
/Dockerfile              ← image build (used by GitHub Actions; you don't run this locally)
/build/                  ← files baked into the image (nginx, php-fpm, supervisord, entrypoint)
/docker/                 ← what you actually deploy
    docker-compose.yml
    .env.example
/app /bootstrap /config /database /public /resources /routes /storage   ← Laravel application
/.github/workflows/      ← CI (do not edit)
```

Image releases are produced by `.github/workflows/release_build.yml` on every push to `main`. The workflow excludes `docker/`, `Dockerfile`, and `README.md` from the source-code release ZIP — that's expected.

<!-- footer -->
---

## 🧑‍💻 Consulting and technical support
* For personal support and queries, please submit a new issue to have it addressed.
* For commercial related questions, please [**contact me**][ivancarlos] for consulting costs.

[cc]: https://docs.github.com/en/communities/setting-up-your-project-for-healthy-contributions/adding-a-code-of-conduct-to-your-project
[contributing]: https://docs.github.com/en/articles/setting-guidelines-for-repository-contributors
[security]: https://docs.github.com/en/code-security/getting-started/adding-a-security-policy-to-your-repository
[support]: https://docs.github.com/en/articles/adding-support-resources-to-your-project
[it]: https://docs.github.com/en/communities/using-templates-to-encourage-useful-issues-and-pull-requests/configuring-issue-templates-for-your-repository#configuring-the-template-chooser
[prt]: https://docs.github.com/en/communities/using-templates-to-encourage-useful-issues-and-pull-requests/creating-a-pull-request-template-for-your-repository
[funding]: https://docs.github.com/en/articles/displaying-a-sponsor-button-in-your-repository
[ivancarlos]: https://ivancarlos.me
