# Deployment Instructions

These notes cover production deployment items that are specific to Web Inventory.

## Azure Ubuntu VM Requirements

Install and configure the normal Laravel runtime:

- PHP 8.5+
- Composer
- Node.js and NPM
- MySQL or MariaDB
- A web server such as Nginx or Apache
- A process manager for queue workers, such as Supervisor or systemd

## Application Setup

From the project directory on the server:

```bash
composer install --no-dev --optimize-autoloader
npm install
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Playwright Rendered Capture Setup

Rendered-page capture requires Node.js and Playwright browser binaries on the server.

Install Chromium and its Ubuntu dependencies:

```bash
npx playwright install --with-deps chromium
```

Enable rendered capture in the production `.env` only when the VM has Playwright working:

```env
RENDERED_CAPTURE_ENABLED=true
RENDERED_CAPTURE_NODE_BINARY=node
RENDERED_CAPTURE_TIMEOUT_SECONDS=12
RENDERED_CAPTURE_SETTLE_MILLISECONDS=1500
RENDERED_CAPTURE_MAX_TEXT_CHARS=50000
RENDERED_CAPTURE_MAX_HTML_CHARS=100000
RENDERED_CAPTURE_VIEWPORT_WIDTH=1366
RENDERED_CAPTURE_VIEWPORT_HEIGHT=900
```

The rendered capture user agent defaults to `RENDERED_CAPTURE_USER_AGENT`, then `RESOLVER_USER_AGENT`, then a fallback value. Set `RESOLVER_USER_AGENT` to a browser-like value if needed.

## Queue Worker

Endpoint resolution and rendered capture should run through the queue worker, not web requests.

Start a worker manually for testing:

```bash
php artisan queue:work
```

In production, run the queue worker under Supervisor or systemd so it restarts automatically.

After deploying code or changing `.env`, restart the queue worker:

```bash
php artisan queue:restart
```

## OpenAI Setup

Follow `OPENAI-API-SETUP.md` to create the OpenAI project and API key, then populate the production `.env`.

Keep these values server-side only:

```env
OPENAI_API_KEY=
OPENAI_MODEL=gpt-5.5
OPENAI_ORGANIZATION=
OPENAI_PROJECT=
OPENAI_TIMEOUT_SECONDS=30
OPENAI_MAX_INPUT_CHARS=50000
OPENAI_ANALYSIS_ENABLED=false
```

Enable AI analysis only after the queue worker, rendered capture, and API key are verified.

## Post-Deploy Checks

Run:

```bash
php artisan migrate:status
php artisan queue:work --once
```

Then verify in the browser:

- Endpoint Profile loads.
- Recheck works.
- Capture Rendered queues and completes.
- Prepared Input JSON includes rendered-page evidence when capture is completed.
