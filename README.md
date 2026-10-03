# Lead Intelligence Dashboard

Find companies worth contacting now — and understand **WHY NOW**.

Lead Intelligence is a B2B prospect research dashboard built with Laravel and Filament. An organization defines its Ideal Customer Profile (ICP), starts an agent run, and reviews qualified companies, buying signals, evidence sources, lead scores, and available decision-maker contacts.

Laravel owns accounts, organization isolation, persistence, notifications, and deterministic scoring. A separate FastAPI service discovers and researches companies, then sends results back to Laravel. FastAPI does not access the Laravel database.

## Features

- **Overview:** organization metrics, score distribution, top opportunities, and recent run activity.
- **ICP Configuration:** product/service, industries, location, employee range, and customer context; shared by users in the same organization.
- **Prospects:** searchable, filterable company list and read-only intelligence details with WHY NOW, buying signals, and evidence links.
- **Contacts:** decision-maker information when returned by the agent; email drafts and meeting links require the user's action. The application does not automatically send outreach or book meetings.
- **Agent Runs:** execution history, status, metrics, related prospects, and safe failure messages.
- **Platform administration:** separate landlord panel for listing and adding organizations and ordinary users.

Each user belongs to one organization. ICP configurations, runs, prospects, and dashboard queries are organization-scoped. Buying signals inherit ownership through their prospect.

## Stack and repository layout

The lockfile currently contains Laravel **13.34.0** and Filament **5.9.0**. The frontend uses Tailwind CSS 4 and Vite 8. Docker runs PHP 8.4, Nginx, and MariaDB 11.

```text
app/                         Laravel application root
  app/Filament/              Application pages, dashboard widgets, landlord pages
  app/Application/           Agent execution, completion, and lead scoring
  app/Infrastructure/        FastAPI HTTP client and response validation
  app/Models/                Organizations, users, ICPs, runs, prospects, signals
  database/migrations/       Database schema
  resources/                 Blade views and Filament theme
  tests/                     PHPUnit unit and feature tests
services/                    Docker PHP and Nginx configuration
docker-compose.yml           Dashboard development services
```

## Requirements

- Git, Docker, and Docker Compose v2.
- Node.js **20.19+ or 22.12+** and npm on the host for building frontend assets. Older Node 20 releases do not satisfy Vite 8's requirements.
- Ports **8092** (dashboard) and **3366** (MariaDB) available locally.
- The separate FastAPI Lead Intelligence service on port **8990** to run real research. Its provider credentials belong in that service's environment, not this repository.

Docker supplies PHP and Composer. For a native installation, use PHP 8.3+ with Laravel's required extensions, including PDO SQLite for tests, and Composer 2.

## Local setup with Docker

### 1. Get the source and environment template

Replace `YOUR_REPOSITORY_URL` with this repository's Git URL:

```bash
git clone YOUR_REPOSITORY_URL leads_find_intelligence_dashboard
cd leads_find_intelligence_dashboard
cp app/.env.example app/.env
```

### 2. Configure Laravel

Edit `app/.env` and set these values for the provided Docker stack:

```dotenv
APP_NAME="Lead Intelligence"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8092

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=leads_find
DB_USERNAME=root
DB_PASSWORD=password

MAIL_MAILER=log

FASTAPI_BASE_URL=http://host.docker.internal:8990
FASTAPI_TIMEOUT=120
FASTAPI_CALLBACK_BASE_URL=http://host.docker.internal:8092
```

The database values above match the existing local Compose configuration. MariaDB is reached at `db:3306` inside Docker and `localhost:3366` from the host. These defaults are for local development.

`app/.env.example` defaults to SQLite; override it with the MySQL settings above to use the included MariaDB container. Leave `APP_KEY` empty until the key-generation step below.

`host.docker.internal` points containers to the host gateway. Using `localhost:8990` from the PHP container would point at PHP's own container, not FastAPI.

### 3. Start the containers

```bash
docker compose up -d --build
docker compose ps
```

The Compose file references `SMTP_PASSWORD`. An unset-variable warning is harmless when using `MAIL_MAILER=log`; email delivery is not needed for dashboard notifications. Never put real credentials in committed configuration.

### 4. Install Laravel dependencies and migrate

Run these commands from the repository root:

```bash
docker compose exec -T -w /var/www/html php composer install
docker compose exec -T -w /var/www/html php php artisan key:generate --no-interaction
docker compose exec -T -w /var/www/html php php artisan migrate --no-interaction
docker compose exec -T -w /var/www/html php php artisan storage:link --no-interaction
```

If MariaDB is still starting, wait until it is ready and retry `migrate`. Use `docker compose logs db` to inspect startup errors. No demo seeding is required.

### 5. Build frontend assets

Run on the host:

```bash
cd app
npm ci
npm run build
cd ..
```

The production build creates `app/public/build/manifest.json` and the application/theme assets. For frontend development, run `npm run dev` from `app/` instead of relying on an old production build.

### 6. Create the first platform administrator

```bash
docker compose exec -w /var/www/html php php artisan make:filament-user --panel=landlord --platform-admin
```

Enter a name, unique email, and password at the interactive prompts. This also creates an organization for the account. There are no default administrator credentials.

Open **http://localhost:8092/landlord** and sign in with the credentials you just created. Use **Organizations → Add organization**, then **Users → Add user** to create an application user in the chosen organization.

To provision an ordinary user from the console instead:

```bash
docker compose exec -w /var/www/html php php artisan make:filament-user --panel=app
```

This creates an organization unless you add `--organization=ID` with an existing organization ID. Only the trusted console `--platform-admin` option grants platform administration.

### 7. Open the application

- Organization dashboard: **http://localhost:8092/app**
- Platform administration: **http://localhost:8092/landlord**

Both panels use the existing User model and `web` authentication guard. Ordinary users cannot access the landlord panel. Platform administrators still see only their own organization's business records in `/app`.

## Connect the FastAPI agent

The dashboard Compose stack does **not** start FastAPI. Obtain and configure the separate agent repository using its own setup instructions. If it is checked out beside this repository as `leads_find_intelligence`, start it with:

```bash
cd ../leads_find_intelligence
docker compose up -d --build
curl http://localhost:8990/health
```

Configure the agent's own provider credentials, such as `OPENAI_API_KEY` and `HUNTER_API_KEY`, in its private environment. For the existing Docker callback flow, the agent's environment must include:

```dotenv
LARAVEL_CALLBACK_BASE_URL=http://host.docker.internal:8092
```

That base URL must match Laravel's `FASTAPI_CALLBACK_BASE_URL`. After changing a Compose service's environment, recreate that service so it picks up the values.

Laravel sends organization ICP data to `POST /api/v1/agent/run`, including a Laravel-generated `run_id` and run-specific callback URL/token. A valid HTTP 202 means research has started. FastAPI later sends a completed or failed result to:

```text
POST /api/v1/agent/runs/{run}/result
Authorization: Bearer <run-specific-token>
```

Laravel validates the response and callback token, persists results transactionally, calculates scores, and creates a database notification for the initiating user. The dashboard polls for updates; no Laravel queue worker, Redis, or websocket server is required for this flow. Legacy synchronous result responses are also supported.

## Using the product

1. Sign in to `/app` as an organization user.
2. Save an **ICP Configuration** with product/service, industries, location, and optional employee range/context.
3. Open **Overview** and click **Run Lead Agent**.
4. Wait for the completion or failure notification; acceptance does not mean research has finished.
5. Open a top opportunity or the **Prospects** list to inspect score, WHY NOW, signals, evidence, and available contacts.
6. Review execution details under **Agent Runs**.

For Hunter discovery, use supported industry labels, for example **Advertising Services** and **Marketing Services**. The existing provider normalizes a few aliases, but arbitrary industry names may be rejected.

Laravel's score is deterministic, from 0–100: ICP match contributes up to 40 points, buying signals up to 30, product relevance up to 20, and evidence quality up to 10. FastAPI returns classifications and evidence, not the authoritative numeric score.

## Tests and quality checks

From the repository root:

```bash
docker compose exec -T -w /var/www/html php php artisan test --compact
docker compose exec -T -w /var/www/html php vendor/bin/pint --dirty --format agent
docker compose exec -T -w /var/www/html php composer validate
docker compose exec -T -w /var/www/html php php artisan route:list --except-vendor
docker compose exec -T -w /var/www/html php php artisan migrate:status
```

The test suite uses in-memory SQLite and mocked external HTTP, including tests for organization isolation, callbacks, scoring, and Filament pages. Production frontend validation is `npm run build` from `app/`.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| Missing Vite manifest or unstyled interface | Run `npm ci` and `npm run build` from `app/`. |
| Database connection refused | Confirm the `db` service is ready and Laravel uses `DB_HOST=db`, `DB_PORT=3306`. |
| Storage/cache permission errors | Ensure the PHP container user can write to `app/storage` and `app/bootstrap/cache`; match local file ownership to your container user. |
| Agent cannot be reached | Check `FASTAPI_BASE_URL`, port 8990, FastAPI health, and connectivity from the PHP container. |
| Accepted run never finishes | Inspect FastAPI logs and confirm its callback base URL is reachable and matches Laravel's configuration. |
| ICP filters rejected | Review industries, country/location, and employee range; Hunter may reject unsupported industry labels. |
| Missing contact details | Contacts depend on the returned provider data; the dashboard does not fabricate people or email addresses. |
| Environment edits have no effect | Run `php artisan config:clear` inside the PHP container; recreate services when their container environment changes. |

Laravel logs are in `app/storage/logs/laravel.log`. Use `docker compose logs php nginx db` for dashboard container logs and the separate agent repository's `docker compose logs agent` for research/provider failures.

Stop the dashboard with `docker compose down`. The database volume persists. Adding `-v` removes that volume and its data.

## Current scope

This is a hackathon MVP with shared-database organization isolation. It does not provide public registration, invitations, tenant roles, billing, scheduled runs, CRM integration, or automatic outreach. External provider availability and supported search filters affect research results. Keep environment files, credentials, and callback tokens private; configure appropriate credentials and HTTPS before deploying publicly.
