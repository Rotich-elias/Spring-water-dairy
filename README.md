# Spring Water Dairy — Backend

Laravel 13 API for the Spring Water Dairy herd-management PWA.

## Stack

- PHP 8.4, Laravel 13, Sanctum token authentication.
- Spatie Laravel Permission for roles and permissions.
- MySQL 8 in Docker; SQLite is supported for local development and used for tests.
- Redis for cache, sessions and queues in Docker.
- `barryvdh/laravel-dompdf` for branded PDF reports.
- Pest for unit and feature tests.

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

The API is served at <http://localhost:8000/api/v1>. The seeded Owner uses
`APP_OWNER_EMAIL` / `APP_OWNER_PASSWORD` from `.env` (defaults:
`owner@springwaterdairy.local` / `change-me-immediately`; change it immediately).

For a quick SQLite setup, set:

```dotenv
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/backend/database/database.sqlite
```

The example environment uses Redis for cache and sessions. If Redis is not running
locally, set `CACHE_STORE=file` and `SESSION_DRIVER=file` instead.

## Tests and formatting

```bash
php artisan test --compact
vendor/bin/pint --dirty --format agent
```

Tests use an in-memory SQLite database and `APP_TIMEZONE=UTC`, so they do not need
MySQL or Redis. `phpunit.xml` defines the test environment.

## Structure

| Path | Purpose |
| --- | --- |
| `routes/api.php` | All versioned API routes under `/api/v1`. |
| `app/Domain/` | Business domains: animals, milk, breeding, health, alerts, reports, settings. |
| `app/Http/Controllers/Api/V1` | API controllers. |
| `app/Http/Requests` | Form requests and validation rules. |
| `app/Models` | Shared models such as users, settings and lookups. |
| `database/seeders` | Roles, permissions, lookups and the initial Owner. |
| `tests/Feature` | Endpoint, authorization and workflow coverage. |

## Scheduled work

`routes/console.php` generates alerts daily at 06:00 and evaluates alert rules. Run the
scheduler with `php artisan schedule:work` locally. In Docker the `scheduler` service
runs it, and the `queue` service processes queued jobs.

## API contract

`../docs/openapi.yaml` is the shared contract (OpenAPI 3.0.3). Keep it in sync with
`routes/api.php`; CI validates the document and rejects unknown or missing operations.

Errors use a consistent envelope:

```json
{
  "error": {
    "code": "validation_failed",
    "message": "The given data was invalid.",
    "fields": { "name": ["The name field is required."] }
  }
}
```

## Docker

The backend image is nginx + PHP-FPM safe: it builds on `php:8.4-fpm`, runs migrations
from `docker-entrypoint.sh`, and is served by the `web` nginx service. The root
`docker-compose.yml` starts the API, frontend, MySQL, Redis, queue and scheduler.
