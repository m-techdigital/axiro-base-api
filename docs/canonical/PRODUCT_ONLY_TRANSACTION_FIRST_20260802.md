# Product-only / Transaction-first Notes - 2026-08-02

## Decision

MBN now treats `Product` as the canonical marketplace asset. The legacy `ProductListing` domain is removed from production code, routes, migrations and storage checks.

`Transaction` is the lifecycle owner for sale, rental, deposit, installment, payment, handover, return, dispute and settlement workflows. Generated documents remain transaction evidence, not a standalone contract module.

## Kept

- `products` with `offer_modes` for sale/rental availability.
- `product_rental_rates`, holds, favorites and availability history under the product owner.
- `transactions.product_id` as the single transaction asset reference.
- `generated_documents`, `document_templates` and acceptances as transaction evidence.
- Compatibility tests proving legacy listing files, tables and routes are absent.

## Removed / Deprecated

- `ProductListing`, `ListingController`, `CustomerListingController`.
- `product_listings`, `listing_favorites`, `listing_rental_rates`.
- Any compatibility migration that reintroduces listing storage.
- Contract as an independent workflow entrypoint. Contract-like files should be generated documents under a transaction.

## Merge Notes

- Do not rebuild marketplace flows around listings.
- Do not add a separate contract CRUD surface for MBN unless a future legal workflow requires it.
- Keep API envelopes and `/api/v1` unchanged.
- Any lifecycle or integrity change must keep feature tests green.
