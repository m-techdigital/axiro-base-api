# Customer and marketplace canonical module

This mini base keeps AXIRO's separation between internal `User` and external `Customer`, while removing company, project, RBAC and CRM-only dependencies. MBN authenticates only through the `customer_api` guard.

Canonical flow: Customer -> Product Listing -> Transaction (`purchase|rental`) -> Contract (`sale|rental`). Buy/sell and rent/lease-out are perspectives of one transaction, not duplicate records.
