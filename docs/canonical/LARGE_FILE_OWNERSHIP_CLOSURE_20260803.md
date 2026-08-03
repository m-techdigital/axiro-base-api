# TRANSACTION PAYMENT CAPTURE OWNERSHIP — 2026-08-03

## Canonical decision

`TransactionPaymentCaptureService` owns payment submission, admin confirmation, product hold at confirmation time, payment-state recalculation and payment confirmation events.

`TransactionLifecycleService` remains the public facade and owns the wider transaction lifecycle. Controller signatures and API routes are unchanged.

Settlement, dispute and rental transitions remain in the lifecycle owner until dedicated rollback and concurrency evidence supports another extraction.
