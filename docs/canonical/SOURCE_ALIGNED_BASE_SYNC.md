# AXIRO Mini Source-Aligned Base Sync v66.37

## Mục tiêu

Sửa lại cách đồng bộ base giữa AXIRO cha và AXIRO Mini: lấy source cha làm chuẩn, copy nguyên khi phù hợp, dùng adapter mỏng khi phạm vi Mini khác, và ghi rõ phần không áp dụng. Không tiếp tục tạo component mới chỉ cùng tên nhưng khác contract.

## Kết quả Admin

### Copy nguyên source AXIRO cha

Các file sau được copy nguyên và được bảo vệ bằng SHA-256 trong `docs/canonical/parent-base-provenance.json`:

- `BaseButton.jsx`
- `BaseConfirmActionButton.jsx`
- `BaseDeleteButton.jsx`
- `BaseFormFooter.jsx`
- `ParentBaseModal.jsx` từ `BaseModal.jsx` của cha
- `tokens/breakpoints.css`
- `tokens/layout.css`
- `primitives/responsive.css`

### Adapter có chủ đích

- `BaseModal.jsx` chỉ chuyển alias cũ (`onOk`, `okText`, `confirmLoading`) sang contract cha (`onSubmit`, `submitText`, `loading`) rồi delegate render cho `ParentBaseModal`.
- `usePermission.jsx` là adapter một admin: giữ API hook của cha nhưng luôn cho phép, không port RBAC/company/project.
- `BaseFilter`, `BaseForm`, `BaseTable`, `BasePageHeader` được ghi rõ là bounded adapter, chưa được gọi là exact parity.

### CSS

`parent-base-modal.css` là chuyển đổi cơ học từ SCSS của cha sang CSS vì Mini không dùng Sass. File được import sau CSS legacy để giữ một owner cuối cùng cho modal. Module CSS bị cấm override `.base-modal`, `.base-form-footer`, `.base-filter`, `.base-table`, `.base-page-header`.

### Gate

`npm run check:parent-source-parity` kiểm tra:

- hash của file copy nguyên;
- file adapter và manifest còn tồn tại;
- module không override selector base.

## Kết quả API

AXIRO cha không có một `ListQueryRequest` hoặc `AppliesListQuery` generic tương đương. Vì vậy Mini giữ các abstraction này ở chế độ `mini_bounded`, không tuyên bố copy nguyên. Manifest API ghi rõ nguồn, lý do và các domain bị loại trừ.

Test `ParentSourceSelectionContractTest` bảo vệ:

- manifest tồn tại;
- có phân loại `mini_bounded`;
- không port Accounting, Reports, HR, Approvals, Calendar.

## Phần không port

- RBAC nhiều vai trò;
- company/project/team scope;
- Accounting, Reports, HR/Payroll;
- approval/workflow engine;
- calendar/map/kanban/editor nếu chưa có nghiệp vụ thật.

## Nguyên tắc phát triển song song

1. Tìm owner tương ứng trong AXIRO cha trước.
2. Copy nguyên nếu dependency-safe và domain-neutral.
3. Nếu cần adapter, adapter chỉ chuyển contract, không render lại implementation.
4. Nếu Mini cần abstraction riêng, ghi rõ `mini_bounded`.
5. Không dùng từ “parity” nếu chưa có source hash hoặc behavior test tương ứng.
