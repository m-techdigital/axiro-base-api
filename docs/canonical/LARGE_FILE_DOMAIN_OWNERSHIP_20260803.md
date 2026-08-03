# Large-file domain ownership closure

## Runtime ownership

- `TransactionLifecycleService` remains the public lifecycle facade and transaction-boundary coordinator.
- `TransactionPaymentCaptureService` owns customer payment submission and admin payment confirmation.
- `TransactionPaymentPlanService` owns payment-plan creation and next-due synchronization.
- `TransactionSettlementService` owns held-fund settlement, rental-deposit refund/deduction, platform-fee posting and cancellation refunds.
- `TransactionActionPolicy` owns customer/admin action availability.
- `TransactionDisputeResolutionService` owns opening, resolving, notifying and settlement hand-off for disputes.

Extracted owners keep database transactions inside their service methods and preserve `TransactionLifecycleService` as the public facade for controllers.
