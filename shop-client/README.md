# mshop client (`shop-client`)

Nuxt 4 storefront + BFF for Slice A auth.

## Setup

1. `cd shop-client`
2. `cp .env.example .env` — set `NUXT_API_BASE=http://localhost:8000`
3. `npm install`
4. `npm run dev` → http://localhost:3000

## BFF

- Browser calls same-origin `/api/*` only.
- Nitro proxies to Laravel, stores Sanctum token in httpOnly `mshop_token` cookie.
- CSRF: `XSRF-TOKEN` cookie + `X-XSRF-TOKEN` header on mutating requests → `403 csrf_mismatch` if missing/invalid.
- Language: `my` (default) / `en` via header toggle; logged-in users sync with `PATCH /api/me/locale`.
