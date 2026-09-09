# Installation

## Requirements

**Server (production):**
- PHP 8.1 or later (with `json`, `mbstring`, `fileinfo` extensions)
- A web server (Apache, Nginx, Caddy)

**Development machine:**
- Node.js 18+ (only needed to build the admin frontend — not required on the server)

## Deployment

### 1. Clone the repository

```bash
git clone https://github.com/your-org/cometcms.git
cd cometcms
```

### 2. Build a deployment package

```bash
make build
```

This compiles the Vue admin frontend and assembles everything into a `dist/` folder. The output is a self-contained PHP application — no Node.js is needed on the server.

### 3. Upload to your server

Upload the **contents** of `dist/` to your server's web root (or a subdirectory). The structure will look like:

```
index.php
router.php
app/
config/
admin/        ← compiled Vue frontend
storage/      ← empty, writable directory for content/users/etc.
```

### 4. Configure your web server

All requests must be routed through `index.php`.

**Apache** (`.htaccess` is included in the build):
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php [QSA,L]
```

**Nginx:**
```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

### 5. Ensure `storage/` is writable

```bash
chmod -R 755 storage/
```

### 6. Open the admin

Navigate to `https://yourdomain.com/admin`. The first-run setup screen will appear.

---

## Configuration (optional)

`config/config.php` lets you adjust:
- `app.timezone` — default `UTC`
- `content.max_revisions` — revision snapshots kept per entry, default `50`
- `cache.ttl` — API cache TTL in seconds
- `security.login_throttle` — brute-force protection limits
- `security.external_submissions` — payload, global rate-limit, and idempotency-cache bounds for the anonymous submission gateway
- `updates.repository_url` — GitHub repository used by the admin update page
- `updates.require_checksum` — require a `.sha256` release asset before installing
- `updates.preserved_paths` — paths skipped when installing release ZIPs
- `webhooks` — outbound webhook URLs

The URL is **auto-detected** from the HTTP request — no `base_url` setting is needed.

The admin update page is opened by clicking the CometCMS version in the sidebar.
Update checks use GitHub releases. Updates are downloaded and verified first,
then installed from the staged package. Installing an update replaces
release-owned application files while preserving the configured paths, including
`storage/` for content, content types, media, users, revisions and other local
data.

CometCMS supports public GitHub releases for update checks and downloads.

---

## Local development

```bash
npm install
make dev
```

This starts the PHP built-in server and the Vite dev server simultaneously. The admin is available at `http://localhost:8000/admin`.
