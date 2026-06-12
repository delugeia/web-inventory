# Web Inventory

A Laravel-based inventory management web application.

## Overview

Web Inventory is a project built with the Laravel 12 framework, focusing on inventory management. It utilizes modern web technologies including Vite for frontend tooling, Tailwind CSS for styling, and Alpine.js for lightweight reactivity.

## Endpoint Resolve

Endpoint Resolve checks stored web endpoints and records the current reachable URL, status code, redirect chain, response timing, selected response headers, DNS details, and normalized failure categories. For bare domain endpoints, Resolve also checks the common canonical URL variants:

- `http://domain.tld/`
- `http://www.domain.tld/`
- `https://domain.tld/`
- `https://www.domain.tld/`

The endpoint detail page reports whether those variants resolve to the preferred HTTPS non-`www` canonical URL, including expandable redirect chains for each variant. The Automation page can run Resolve in batches, including a batch size of `1` for a single queued endpoint.

## Tech Stack

- **Language:** PHP 8.5+
- **Framework:** Laravel 12.x
- **Frontend Tooling:** Vite, Tailwind CSS, Alpine.js
- **Package Managers:** Composer (PHP), NPM (JavaScript)
- **Database:** MySQL (configured in `.env`), with SQLite support for development/testing.

## Requirements

- PHP >= 8.5
- Composer
- Node.js & NPM
- MySQL or another supported database engine
- Required PHP extensions: ctype, curl, dom, fileinfo, filter, hash, mbstring, openssl, pdo, pdo_mysql, pdo_sqlite, session, tokenizer, xml, zip

## Setup

1.  **Clone the repository:**
    ```bash
    git clone <repository-url>
    cd web-inventory
    ```

2.  **Run the setup script:**
    The project includes a comprehensive setup command in `composer.json` that installs dependencies, prepares the environment file, generates the application key, runs migrations, and builds frontend assets.
    ```bash
    composer run setup
    ```

    *Alternatively, perform steps manually:*
    - `composer install`
    - `cp .env.example .env`
    - `php artisan key:generate`
    - `php artisan migrate`
    - `npm install`
    - `npm run build`

## Running the Application

To start the development environment (including the local server, queue listener, and Vite dev server), run:

```bash
composer run dev
```

The application will be accessible at the URL defined in your `.env` file (default: `http://web-inventory.test` or `http://localhost:8000` if using `php artisan serve`).

## Scripts

Available scripts defined in `composer.json`:

- `composer run setup`: Full project initialization (install deps, env setup, migrations, frontend build).
- `composer run dev`: Runs `php artisan serve`, `php artisan queue:listen`, and `npm run dev` concurrently.
- `composer run test`: Clears config cache and runs PHPUnit tests.

Available NPM scripts:

- `npm run dev`: Starts the Vite development server.
- `npm run build`: Builds frontend assets for production.

## Environment Variables

Key environment variables in `.env`:

- `APP_NAME`: Name of the application.
- `APP_ENV`: Application environment (local, production, etc.).
- `APP_KEY`: Application encryption key.
- `APP_URL`: The URL used to access the application.
- `DB_CONNECTION`: Database driver (e.g., `mysql`, `sqlite`).
- `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`: Database connection details.

## Tests

To run the automated test suite:

```bash
composer run test
```

Tests are located in the `tests/` directory and use PHPUnit.

## Deployment Notes

After pulling changes that include new migrations or frontend view updates, run:

```bash
php artisan migrate
npm run build
```

Existing endpoint rows keep their stored values until they are rechecked. New Resolve fields such as canonical URL coverage, DNS record values, response timing, and selected headers are populated on the next Resolve/Recheck run.

## Project Structure

- `app/`: Core application logic (Models, Controllers, Services, etc.).
- `bootstrap/`: Application bootstrapping and cache files.
- `config/`: Configuration files for various components.
- `database/`: Database migrations, factories, and seeders.
- `public/`: Publicly accessible assets and entry point (`index.php`).
- `resources/`: Uncompiled frontend assets (CSS, JS, Views).
- `routes/`: Application route definitions (web, api, console).
- `storage/`: Logs, compiled templates, and file uploads.
- `tests/`: Feature and Unit tests.
- `vendor/`: Composer dependencies.
- `node_modules/`: NPM dependencies.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT). 
TODO: Confirm if this project uses a different license.
