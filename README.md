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
