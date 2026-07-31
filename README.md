# AXIRO Base API

Canonical Laravel mini-base extracted from AXIRO/Mylands. Includes JWT authentication, Products, Transactions, Contracts, dashboard statistics, validation, pagination, and integration tests.

## Setup
```bash
cp .env.example .env
composer install
touch database/database.sqlite
php artisan key:generate
php artisan jwt:secret
php artisan migrate:fresh --seed
php artisan test
php artisan serve
```
Default development login is controlled by `ADMIN_USERNAME` and `ADMIN_PASSWORD`.

## Customer storefront authentication

MBN and other public storefronts must use `/api/v1/auth/customer/*`. Internal administration continues to use `/api/v1/login`, `/refresh`, `/logout`, and `/me`.

## Dữ liệu mẫu Marketplace

Sau khi chạy `php artisan migrate:fresh --seed`, hệ thống có sẵn các kịch bản mua bán, thuê, trả góp, thanh toán, bàn giao, hoàn trả và tranh chấp. Xem `docs/MARKETPLACE-DEMO-SCENARIOS.md`.


## Customer session and refresh cookie

For local development, use the frontend Vite proxy and keep the customer cookie host-only:

```env
FRONTEND_URL=http://localhost:5174
FRONTEND_URLS=http://localhost:5174,http://127.0.0.1:5174
CUSTOMER_AUTH_ACCESS_TTL_MINUTES=43200
CUSTOMER_AUTH_REFRESH_COOKIE_TTL_DAYS=30
CUSTOMER_AUTH_REFRESH_COOKIE_PATH=/
CUSTOMER_AUTH_REFRESH_COOKIE_DOMAIN=
CUSTOMER_AUTH_REFRESH_COOKIE_SECURE=false
CUSTOMER_AUTH_REFRESH_COOKIE_SAME_SITE=lax
```

Do not mix `localhost` and `127.0.0.1` when using an absolute API URL. The frontend now defaults to `/api/v1` through the Vite proxy, which avoids that mismatch.

For an intentional HTTPS cross-origin deployment, configure both sides explicitly:

```env
CUSTOMER_AUTH_REFRESH_COOKIE_DOMAIN=.example.com
CUSTOMER_AUTH_REFRESH_COOKIE_SECURE=true
CUSTOMER_AUTH_REFRESH_COOKIE_SAME_SITE=none
FRONTEND_URL=https://www.example.com
FRONTEND_URLS=https://www.example.com
```

## Demo seed data

Marketplace demo records are created only in `local` and `testing` by default. Production-like environments must opt in explicitly:

```env
SEED_MARKETPLACE_DEMO=true
```

Leave the variable unset or set it to `false` outside local/demo environments to avoid creating sample customers, listings, transactions, wallets, notifications, or documents.
