# socialtokenizator

<!-- buttons -->
[![Stars](https://img.shields.io/github/stars/ivancarlosti/socialtokenizator?label=⭐%20Stars&color=gold&style=flat)](https://github.com/ivancarlosti/socialtokenizator/stargazers)
[![Watchers](https://img.shields.io/github/watchers/ivancarlosti/socialtokenizator?label=Watchers&style=flat&color=red)](https://github.com/sponsors/ivancarlosti)
[![Forks](https://img.shields.io/github/forks/ivancarlosti/socialtokenizator?label=Forks&style=flat&color=ff69b4)](https://github.com/sponsors/ivancarlosti)
[![Downloads](https://img.shields.io/github/downloads/ivancarlosti/socialtokenizator/total?label=Downloads&color=success)](https://github.com/ivancarlosti/socialtokenizator/releases)
[![GitHub commit activity](https://img.shields.io/github/commit-activity/m/ivancarlosti/socialtokenizator?label=Activity)](https://github.com/ivancarlosti/socialtokenizator/pulse)
[![GitHub Issues](https://img.shields.io/github/issues/ivancarlosti/socialtokenizator?label=Issues&color=orange)](https://github.com/ivancarlosti/socialtokenizator/issues)  
[![License](https://img.shields.io/github/license/ivancarlosti/socialtokenizator?label=License)](LICENSE)
[![GitHub last commit](https://img.shields.io/github/last-commit/ivancarlosti/socialtokenizator?label=Last%20Commit)](https://github.com/ivancarlosti/socialtokenizator/commits)
[![Security](https://img.shields.io/badge/Security-View%20Here-purple)](https://github.com/ivancarlosti/socialtokenizator/security)
[![Code of Conduct](https://img.shields.io/badge/Code%20of%20Conduct-2.1-4baaaa)](https://github.com/ivancarlosti/socialtokenizator?tab=coc-ov-file)
<!-- endbuttons -->

A self-hosted, single-container image-sharing web app:

- Public, anonymous browsing — every image gets a stable, shareable UUID URL.
- Private uploads behind a pluggable auth layer (`none` / `account` / `keycloak`).
- Object storage on **Cloudflare R2** (S3-compatible).
- MySQL for metadata, tags, and source links.
- Server-rendered Open Graph + Twitter Card tags so links unfurl perfectly on X and Facebook.
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
6. [Reverse-proxy examples](#reverse-proxy-examples)
7. [Operating the app](#operating-the-app)
8. [Troubleshooting](#troubleshooting)
9. [Repository layout](#repository-layout)

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
