## 2026-08-05 — Direct escrow agreement flow

- Added a private escrow invitation flow that does not require a public marketplace listing.
- The initiator selects buyer/seller role, counterparty username, asset terms, amount, delivery method and inspection policy.
- No payment obligation is created until both parties accept the immutable agreement snapshot.
- Accepted agreements reuse canonical payment, handover, dispute, document, settlement and payout owners.


## 2026-08-05 — Digital asset escrow handover

- Added Mini-bounded escrow handover policy for game accounts and in-game items.
- Reused canonical transaction, payment, snapshot, dispute, document and payout owners.
- Browser/release evidence must be regenerated after this source change.


### Escrow document and demo closure

- Marketplace documents use transaction-level immutable handover snapshots for both game accounts and in-game items.
- The handover and safety-check templates include delivery method, inspection duration/deadline, evidence requirement, and non-sensitive confirmation notes.
- Demo and transactional E2E source cover an `in_game_trade` item flow in addition to account rental/sale flows.
- Secret credentials remain explicitly excluded from database fields, document merge data, notes, and browser forms.

## 2026-08-05 — Digital asset escrow source-package closure

- Removed browser evidence directories from Admin/MBN source trees and removed API `public/storage` runtime content from the source package.
- Release-package guards now fail closed on `artifacts`, coverage/build output and API public runtime storage; regression probes confirmed the guards reject these paths before packaging.
- Digital asset escrow source/static guards pass across API, Admin and MBN. Runtime browser, transactional E2E, `release:all` and hash-finalized evidence remain pending until the source is committed and pushed in the three Git repositories.

## 2026-08-05 — Escrow Box private module

- Replaced username-based direct escrow with a dedicated private Escrow Box aggregate.
- Added one-time hashed invite claim, alias-only customer presenter and contact-information validation.
- Added versioned agreement, Admin review, fee rule/snapshot/override, payment obligations and ordered handover checkpoints.
- Added private optimized media pipeline and dispute retention lock.
- Reused canonical transaction payment, wallet, settlement, dispute, document and payout owners.
- Runtime browser/E2E/release evidence is pending after this source change.

## 2026-08-05 184710 style-baseline carry-forward

- The MBN 184710 deterministic stylesheet manifest, desktop/mobile shell owners, and style architecture guards are retained as the active UI baseline.
- Escrow Box routes, hooks, repositories, privacy contract, and media/handover flows are carried forward onto that baseline.
- Escrow Box CSS is registered once in `src/index.css`; route components do not side-effect import CSS.
- API/Admin Escrow Box owners remain `mini_bounded`; no company, RBAC, HR, accounting, report, or project runtime is introduced.
- Previous build/browser/release evidence is stale after this merge and must be regenerated from clean pushed Git HEADs.
