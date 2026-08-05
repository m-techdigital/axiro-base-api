# Database schema
Core graph: `users`, `customers`, `products`, `transactions`, `generated_documents`, wallets, payouts, notifications and Escrow Box tables.

`Transaction` is the lifecycle owner. Documents derive from transaction snapshots; Mini does not own a standalone Contract table/module.
