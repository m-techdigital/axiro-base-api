# Marketplace options cache, dispute timeline và rental E2E - 2026-08-02

## Quyết định

- `/api/v1/marketplace/options` là owner runtime cho document type và dispute outcome.
- Endpoint công bố `options_version`, `options_hash`, TTL cache, ETag và cache headers.
- `marketplace-contract.json.option_catalog` là fallback được sinh từ cùng contract snapshot; frontend không giữ bảng label viết tay thứ hai.
- Customer transaction timeline hiển thị outcome tranh chấp từ event metadata bằng label options API/fallback contract.
- Rental lifecycle được bảo vệ bằng E2E cho cả hoàn tất bình thường và tranh chấp hủy/hoàn tiền.

## Ranh giới

Không bổ sung Contract nghiệp vụ, RBAC, company/project/team, generic SLA/fraud engine hoặc Accounting reconciliation.

## Migration

Dự án đang phát triển nên schema canonical tiếp tục nằm trong migration gốc. Không thêm migration compatibility cho dữ liệu legacy ở vòng này; alias document type vẫn chỉ là read compatibility.

## Tham chiếu AXIRO cha

- Giữ pattern enum/options từ backend giống `DocumentTemplateType::options()` và `GeneratedDocumentStatus::options()`.
- Giữ FormRequest/lifecycle service là mutation boundary; UI không chỉnh trạng thái transaction thô.
- Chỉ học pattern cache/option/catalog cần cho Mini; không port approval center, document compliance, accounting sync, import/export recovery hoặc system operations.

## Đầu việc tiếp theo

- Thêm options invalidation theo contract version ở Admin/Customer nếu cache client sống lâu hơn TTL.
- Bổ sung transaction timeline cho admin giống customer để nhìn outcome tranh chấp ngay trong chi tiết giao dịch.
- Thêm E2E cho rental quá hạn và hoàn trả có khấu trừ cọc.
- Khi bắt đầu có DB dùng thật, tách migration thêm `marketplace_disputes.outcome` thành migration compatibility riêng.
