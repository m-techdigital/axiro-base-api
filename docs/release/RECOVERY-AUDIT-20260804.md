# Recovery Audit — 2026-08-04

## Phạm vi

Đối chiếu source hiện tại với các artifact trọng yếu từ khoảng 18:56 đến baseline 23:19 bằng file inventory, content diff và semantic markers. Không dùng tên ZIP để suy đoán phiên bản.

## Kết luận

- Không phát hiện capability runtime đã từng có trong các artifact trọng yếu nhưng bị mất khỏi source hiện tại.
- Source hiện tại là superset về file chức năng; các file cũ không còn chủ yếu là file đã đổi tên/đổi sang SCSS, checker versioned cũ, cache hoặc runtime artifact.
- Đã phục hồi `.env.example` và thêm `.env.production.example`; đây là các template từng có nhưng bị rơi khỏi các package sau khi hardening quy tắc `.env*`.
- Đã làm sạch package: bỏ SQLite runtime và ảnh test sinh ra khỏi ZIP API.

## Artifact đã đối chiếu

- `axiro-base-api-final-canonical-closure-20260804-clean.zip`
- `axiro-base-api-bundle-security-visual-closure-20260804-clean.zip`
- `axiro-base-api-20260804-2217-clean.zip`
- `axiro-base-api-20260804-2232-clean.zip`
- `axiro-base-api-route-css-release-20260804-clean.zip`

## Guard mới

`recovery-baseline.json` liệt kê các owner/capability bắt buộc. `check:recovery-baseline` fail nếu một baseline cũ làm mất owner, env template hoặc đưa runtime artifact trở lại.

## Quy tắc carry-forward

1. ZIP mới không tự động là source of truth.
2. So sánh Git hash hoặc manifest trước khi áp dụng.
3. Nếu repo chỉ thay docs/evidence, phải ghi rõ `docs_only`.
4. Release atomic chỉ hợp lệ khi ba hash trong release summary khớp ba HEAD đã push.
