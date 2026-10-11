# mshop API (`shop-server`)

Laravel 13 API for the mshop marketplace. Slice A: auth, roles, Neon Free Postgres.

## Prerequisites

- PHP 8.3+ with `pdo_pgsql`
- Composer
- A [Neon](https://neon.com/) Free project (this repo uses project `mshop` — Postgres 16)
- Optional: Docker for [Mailpit](https://github.com/axllent/mailpit) (password-reset email UI)

## Local setup

1. `cd shop-server`
2. `cp .env.example .env`
3. Paste the Neon **direct** (non-pooler) connection string into `DB_URL` for local migrations. Keep `DB_CONNECTION=pgsql` and `DB_SSLMODE=require`. (Pooled/`-pooler` URLs break DDL/migrations.)
4. For tests: `cp .env.testing.example .env.testing`, set `DB_URL` to the **`mshop_test` branch** direct URI (never the main branch).
5. `composer install`
6. `php artisan key:generate`
7. `php artisan migrate:fresh --seed` — loads Slice A role fixtures (local/testing only)
8. `php artisan serve` → API at `http://localhost:8000`
9. `php artisan test` (uses Neon `mshop_test` via `.env.testing`)
10. Optional mail: `docker compose up -d` → Mailpit at `http://localhost:8025`; set `MAIL_MAILER=smtp`, `MAIL_PORT=1025`

### Neon branches

| Branch | Use |
|--------|-----|
| `main` | Local/dev app data |
| `mshop_test` | Pest / `RefreshDatabase` only |

Never commit real `DB_URL` values. Never run tests against the main branch.

## Auth settings (env)

| Key | Default | Purpose |
|-----|---------|---------|
| `AUTH_TOKEN_TTL_DAYS` | 30 | Sanctum token `expires_at` |
| `AUTH_REQUIRE_EMAIL_VERIFICATION` | false | Off in MVP; register logs in immediately |
| `AUTH_LOGIN_MAX_ATTEMPTS` / `AUTH_LOGIN_DECAY_MINUTES` | 5 / 15 | Login rate limit (EXAMPLE) |
| `TRUSTED_PROXIES` | 127.0.0.1 | Trust Nuxt BFF `X-Forwarded-For` |
| `FRONTEND_URL` | http://localhost:3000 | Password-reset links |

## Smoke (Slice A)

With `php artisan serve` running:

```bash
php scripts/smoke_slice_a.php
```

## Local seed users (local/testing only)

Password for all: `password`

| Role | Email |
|------|-------|
| admin | admin@example.test |
| ops | ops@example.test |
| finance | finance@example.test |
| reseller | reseller@example.test |
| rider | rider@example.test |
| customer | customer@example.test |

Seeder refuses to run outside `local` / `testing`.
