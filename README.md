# mshop

Monorepo for the mshop application: a **Nuxt** storefront (`shop-client`) and a **Laravel** API/backend (`shop-server`).

## Repository layout

| Path | Stack | Role |
|------|--------|------|
| `shop-client/` | Nuxt 4, Vue 3 | Frontend |
| `shop-server/` | Laravel 13, Vite, Tailwind 4 | Backend |

## Prerequisites

- **Node.js** (LTS) and npm
- **PHP** ^8.3, **Composer**
- Database and other services as configured in `shop-server/.env`

## Getting started

### Backend (`shop-server`)

```bash
cd shop-server
cp .env.example .env   # if .env does not exist yet
composer install
php artisan key:generate
npm install
```

Run the Laravel app (default `http://localhost:8000`):

```bash
php artisan serve
```

Frontend assets (Vite):

```bash
npm run dev
```

### Frontend (`shop-client`)

```bash
cd shop-client
npm install
npm run dev
```

Dev server: `http://localhost:3000`.

## Common scripts

**shop-client**

| Command | Description |
|---------|-------------|
| `npm run dev` | Nuxt dev server |
| `npm run build` | Production build |
| `npm run preview` | Preview production build |

**shop-server**

| Command | Description |
|---------|-------------|
| `php artisan serve` | HTTP server |
| `npm run dev` | Vite dev (Laravel assets) |
| `npm run build` | Vite production build |
| `composer test` / `./vendor/bin/pest` | Tests (Pest) |

## Environment

- Copy `shop-server/.env.example` to `shop-server/.env` and configure the app.
- Do not commit `.env` files; secrets stay local.

## Further reading

- [Nuxt docs](https://nuxt.com/docs)
- [Laravel docs](https://laravel.com/docs)
- Per-app notes: `shop-client/README.md`, `shop-server/README.md`
