# Next Backlog

- Visual smoke bằng browser cho Admin command center và Customer journey.
- E2E notification deep-link và payout verification.
- Additive migration khi baseline chuyển sang DB giữ dữ liệu.
- Đo first-load thực tế trước khi tách thêm AntD/shared chunks.

## Post-closure verification

1. Run a fresh production build and record Admin route closures plus MBN initial JS/CSS after active-panel and route-style ownership changes.
2. Continue owner-level Admin route closure reduction; the initial graph is under budget, but heavy route closures still exceed 650 KB.
3. Reduce the MBN initial CSS payload after the route-owned CSS split; JS initial is measured under 650 KB, CSS remains heavy.
4. Run Admin and MBN multi-viewport visual regression gates and retain screenshots as release artifacts.
5. Run `composer release:all`, finalize hash-matched evidence, then regenerate release artifacts from the finalized HEAD.

## Sau vòng route/CSS closure

1. Chạy Admin và MBN `build:analyze` để ghi số đo mới sau route owner/CSS owner split.
2. Chạy `composer release:all` từ fresh DB với browser credentials và LibreOffice.
3. Finalize evidence chỉ từ release summary có hash khớp ba HEAD đã push.
4. Chỉ tối ưu tiếp owner nào còn vượt budget theo bundle report mới; không khôi phục shared AntD vendor.

## Required verification after 2026-08-05 owner split

1. Run Admin and MBN dependency install, lint and `build:analyze` in the supported Node runtime; record fresh initial and route-closure sizes.
2. Run Admin/MBN visual regression at desktop, tablet and mobile widths after the route/CSS split.
3. Run API targeted/full tests even though API runtime is unchanged, because release evidence must represent one synchronized three-repo source set.
4. Commit and push all three repositories, run `composer release:all`, finalize only a hash-matched passed summary, then package directly from the finalized clean HEADs.
5. Continue splitting only when the fresh bundle report identifies a real route/global dependency owner; do not create cosmetic files or reintroduce shared AntD vendor chunks.


## Digital asset escrow release verification

1. Run fresh migration/seed and the API escrow/document tests on the canonical database matrix.
2. Run MBN transactional E2E for account rental and `ITEM-0901` in-game item handover, including snapshot, inspection deadline, buyer confirmation, documents and payout continuity.
3. Run Admin/MBN visual regression at desktop, tablet and mobile for product configuration, transaction escrow panel, snapshot evidence and handover modal.
4. Finalize only from a passed `release:all` summary whose three repository hashes match clean pushed HEADs.

## Escrow Box release execution

- Run fresh migration/seed, targeted EscrowBoxWorkflowTest, full PHPUnit/Pint.
- Run Admin/MBN lint/build and browser visual regression for create/join/detail/admin review at desktop/tablet/mobile.
- Run transactional E2E for equal exchange, top-up payment, fee payer modes, ordered handover, receipt confirmation and dispute.
- Run `composer release:all` from clean pushed HEADs and finalize hash-matched evidence.

## Escrow Box remaining release evidence

- Run focused and full PHPUnit/Pint for validation, phone invite cancel/replace, privacy, and settlement races.
- Run Admin/MBN lint and production builds on Node >=22.22.0.
- Add browser coverage for detail loading, nested field errors, horizontal exchange, cancel/replace invite, and mobile layouts.
- Run transactional Escrow Box E2E, then `release:all` and hash-finalize evidence from clean pushed HEADs.

