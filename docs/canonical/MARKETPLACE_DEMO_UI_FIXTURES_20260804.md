# Marketplace Demo UI Fixtures — 2026-08-04

The canonical demo seed now provides stable records for Admin browser verification beyond product and transaction basics:

- customer wallets and ledger history;
- submitted and paid payout requests;
- seller verification and payout accounts;
- open/resolved trust-risk flags;
- published and draft trust/content entries;
- a marketplace review linked to a completed transaction;
- issued marketplace documents through `MarketplaceDocumentSeeder`.

Public sale/rental/installment products remain separate from historical transaction inventory so browser tests can mutate them without inherited holds.
