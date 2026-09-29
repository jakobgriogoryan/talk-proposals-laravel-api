# Environment Configuration

Copy `.env.example` to `.env`, generate a local application key, and set values for the environment where the API runs:

```bash
cp .env.example .env
php artisan key:generate
```

Keep `.env` local and untracked. Never place real application keys, database passwords, Pusher secrets, Algolia admin keys, or other private credentials in this file or any tracked documentation.

## Core application

```env
APP_NAME="Talk Proposals API"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000
```

Generate `APP_KEY` with `php artisan key:generate`; do not copy a key from another environment.

## Database

```env
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/database.sqlite
```

Use the matching `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` values when using MySQL or PostgreSQL.

## Sanctum SPA authentication

```env
SANCTUM_STATEFUL_DOMAINS=localhost:5173,127.0.0.1:5173
SESSION_DOMAIN=localhost
SESSION_DRIVER=file
```

The Vue SPA must request `/sanctum/csrf-cookie` before state-changing stateful requests such as login and logout.

## Broadcasting with Pusher

```env
BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=<pusher-app-id>
PUSHER_APP_KEY=<pusher-public-key>
PUSHER_APP_SECRET=<pusher-secret>
PUSHER_APP_CLUSTER=<pusher-cluster>
```

Only the Pusher public key and cluster belong in frontend `VITE_*` variables. The app secret must remain backend-only.

## Search with Algolia

```env
SCOUT_DRIVER=algolia
ALGOLIA_APP_ID=<algolia-application-id>
ALGOLIA_SECRET=<algolia-admin-api-key>
```

For local development without Algolia, use the repository-supported non-network Scout driver instead of real credentials.

## Credential rotation

Older revisions of this document contained real-looking application and Pusher values. Removing them from the current tree does not revoke them or erase Git history. If those values were ever active, rotate them in the relevant provider account.
