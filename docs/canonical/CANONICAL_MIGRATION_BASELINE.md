# Canonical migration baseline

The project is currently in active development. Database setup uses the canonical original migrations only.

- `products` owns product identity, commercial configuration, approval, publication and availability.
- `offer_modes` and `model_offer_modes` own `sell`/`rent` capabilities.
- installment remains a product sale capability through `installment_enabled` and typed term columns.
- `transactions.product_id` is canonical; no listing relation exists.
- reviews and favorites reference products directly.
- compatibility migrations under `2026_08_01_*` were removed.

Local development should rebuild using:

```bash
php artisan migrate:fresh --seed
```

Do not reintroduce listing migration or legacy storage columns (`transaction_types`, `sale_enabled`, `rental_enabled`, `listing_id`).
