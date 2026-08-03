# Architecture
Laravel API with internal admin auth and customer marketplace auth. Controllers stay thin, validation belongs to FormRequest classes, resources/presenters own customer-safe output and lifecycle changes run through services inside database transactions.

Recent split boundaries:

- Marketplace document payload assembly lives in `MarketplaceDocumentPayloadBuilder`.
- Marketplace document rendering lives in `MarketplaceDocumentRenderer`.
- Withdrawal state validation/transition lives in `WithdrawalStateTransitionService`.

Mini follows AXIRO parent response, validation, resource, service and audit conventions while excluding company/RBAC/project/accounting/report/HR dependencies.
