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
