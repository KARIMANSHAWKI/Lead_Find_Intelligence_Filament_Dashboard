# Lead Intelligence Dashboard

A Laravel + Filament dashboard that helps you find companies worth contacting and understand **WHY NOW**. Configure your ideal customer, run the agent, and review prospects, scores, buying signals, and available contacts.

## Requirements

- Docker and Docker Compose
- Node.js 20.19+ or 22.12+ and npm
- The separate FastAPI agent service for running research

## Setup

### 1. Download the project

Replace `YOUR_REPOSITORY_URL` with your GitHub repository URL.

```bash
git clone YOUR_REPOSITORY_URL leads_find_intelligence_dashboard
cd leads_find_intelligence_dashboard
cp app/.env.example app/.env
```

### 2. Update the environment

Open `app/.env` and set:

```dotenv
APP_URL=http://localhost:8092

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=leads_find
DB_USERNAME=root
DB_PASSWORD=password

FASTAPI_BASE_URL=http://host.docker.internal:8990
FASTAPI_TIMEOUT=120
FASTAPI_CALLBACK_BASE_URL=http://host.docker.internal:8092
```

These database credentials are for the included local Docker setup. Keep your `.env` private.

### 3. Start Docker and install the app

Run from the project folder:

```bash
docker compose up -d --build
docker compose exec -T -w /var/www/html php composer install
docker compose exec -T -w /var/www/html php php artisan key:generate --no-interaction
docker compose exec -T -w /var/www/html php php artisan migrate --no-interaction
```

### 4. Build the frontend

```bash
cd app
npm ci
npm run build
cd ..
```

### 5. Create an admin account

```bash
docker compose exec -w /var/www/html php php artisan make:filament-user --panel=landlord --platform-admin
```

Enter your name, email, and password when prompted.

Open **http://localhost:8092/landlord** to sign in. Add organizations and users from this panel.

### 6. Start the agent service

Set up the separate FastAPI project using its README. In its `.env`, configure provider keys and set:

```dotenv
LARAVEL_CALLBACK_BASE_URL=http://host.docker.internal:8092
```

Run these commands from the FastAPI project folder:

```bash
docker compose up -d --build
curl http://localhost:8990/health
```

## Use the dashboard

Open **http://localhost:8092/app** and sign in.

1. Save your **ICP Configuration**.
2. Click **Run Lead Agent** on Overview.
3. Wait for the completion notification.
4. Review **Prospects** and **Agent Runs**.

Users in the same organization share data. Other organizations cannot access it.

## Run tests

From the dashboard project folder:

```bash
docker compose exec -T -w /var/www/html php php artisan test --compact
```

## Stop the app

```bash
docker compose down
```
