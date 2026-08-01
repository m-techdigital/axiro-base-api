# AXIRO Mini Parent-First Development Policy

**Ngày ban hành:** 2026-08-02  
**Áp dụng cho:** `axiro-base-admin`, `axiro-base-api`, `mbn-react`  
**Nguồn đối chiếu hiện tại:** `mylands-admin-develop-20260801-d0af27d9.zip`, `mylands-api-develop-20260801-98fe973.zip`

## 1. Mục tiêu bắt buộc

AXIRO cha là chuẩn kiến trúc và kỹ thuật. AXIRO Mini chỉ thu hẹp phạm vi nghiệp vụ, không được tự tạo một implementation khác chỉ vì cùng tên component/service.

Mỗi thay đổi liên quan base, hook, CSS, Product, Transaction, payment, availability, document hoặc API contract phải bắt đầu bằng việc đọc source cha, import dependency, consumer thật, test và migration liên quan.

## 2. Kết luận kiểm tra lại AXIRO cha

### 2.1 Product và offer mode

AXIRO cha phân biệt rõ:

- `product_type`: loại bản thân sản phẩm.
- `offer_modes`: mục đích thương mại của sản phẩm.
- `OfferModeCode`: `sell`, `rent`.
- Trả góp không phải offer mode; là capability/điều kiện của bán.

Source chuẩn:

- API: `app/Enums/OfferModeCode.php`
- API: `app/Models/OfferMode.php`
- API: `app/Models/Traits/HasOfferModes.php`
- API: `database/migrations/2026_05_25_213237_create_offer_modes_table.php`
- API: `database/migrations/2026_05_25_213251_create_model_offer_modes_table.php`
- Admin: `src/modules/products/tabs.js`
- Admin: `src/modules/products/filters.jsx`
- Admin: `src/modules/products/columns.js`

Mini phải giữ contract canonical:

```text
product_type = loại tài sản
offer_modes = [sell, rent]
installment_enabled = capability riêng của sell
```

### 2.2 Approval và publication

AXIRO cha tách:

- `approval_status`: trạng thái kiểm duyệt.
- `is_published`: trạng thái công khai.
- `availability_status`: trạng thái phân bổ/tồn tại thương mại.

Không được dùng một field `status` để đại diện đồng thời cả ba.

### 2.3 Product selection context

AXIRO cha dùng `ProductSelectionService` và `ProductSelectionContext` để tránh mỗi controller tự viết điều kiện lựa chọn sản phẩm.

Mini được phép rút gọn context còn:

- `management`
- `public_marketplace`
- `transaction`
- `rental`
- `admin_review`

Nhưng phải giữ pattern chung: một service canonical, một lý do disabled, một `assertSelectable()`.

### 2.4 Availability và concurrency

AXIRO cha có:

- `ProductAvailabilityStatus`
- `ProductHold`
- `ProductAvailabilityHistory`
- `ProductInventoryService`
- row lock
- allocation version
- source ownership
- hold expiry
- transition validation
- integrity commands và MySQL concurrency tests

Mini không copy nguyên `ProductInventoryService` vì cha phụ thuộc Reservation, Contract, Company, Project và CRM. Mini phải chắt lọc đúng core:

- `lockForUpdate()` trước mọi transition.
- `expected_version` hoặc kiểm tra version khi có client action.
- transition matrix rõ ràng.
- một active hold hợp lệ.
- source_type/source_id là owner của hold.
- release phải đúng source hoặc policy Admin.
- history sau mỗi transition.
- scheduler release hold hết hạn.
- test concurrent hold/double purchase trên MySQL.

### 2.5 Admin base architecture

AXIRO cha dùng schema-driven module thay vì page tự dựng:

- Product form: `BaseFormPage`.
- Product fields: `tabs.js`.
- Product list: `columns.js`, `filters.jsx`.
- List owner: `BaseListView` + `useBaseFilters` + `BaseTable`.
- Form owner: `BaseForm` với field registry, server error mapping, computed/dependent fields và canonical footer.
- Action owner: declarative action schema.

Mini chỉ được tự giữ implementation riêng khi dependency cha lệch domain và đã ghi rõ `mini_bounded`.

## 3. Ma trận tích hợp bắt buộc

Mọi phần phải được phân loại trước khi code:

### `exact_source`

Copy nguyên source cha khi toàn bộ import/dependency phù hợp Mini. Phải lưu SHA-256 và có gate kiểm tra.

### `mechanical_conversion`

Chỉ chuyển cú pháp không đổi hành vi, ví dụ SCSS sang CSS vì Mini không dùng Sass. Phải ghi source và checksum đầu vào.

### `bounded_adapter`

Adapter mỏng, chỉ chuyển prop/response hoặc loại dependency company/RBAC. Adapter không được tự render lại implementation cha.

### `mini_bounded`

Foundation riêng của Mini khi cha không có abstraction tương đương hoặc abstraction cha phụ thuộc domain bị loại. Phải ghi rõ không tuyên bố parity.

### `deferred`

Có giá trị nhưng chưa đủ dependency closure/consumer thật.

### `excluded`

Không áp dụng vì lệch nghiệp vụ Mini.

## 4. Phần chung và phần riêng hiện tại

### Dùng chung với AXIRO cha

- `offer_modes`: `sell`, `rent`.
- `HasOfferModes` semantics: `null = ignore`, `[] = detach all`.
- `product_type` tách khỏi offer mode.
- approval/publication/availability tách riêng.
- Product selection context.
- availability row lock/version/history/hold.
- schema-driven Product form/list/filter/action.
- API FormRequest, Resource, response envelope, request/correlation ID.
- CSS/base owner, không override tại module.

### Mini giữ riêng

- `game_code`, `server_name`, `level`, thuộc tính tài khoản game.
- `installment_enabled` và điều kiện trả góp.
- rental rates và billing modes đặc thù MBN.
- owner là customer, không company/project/assigned user.
- một admin, không permission graph phức tạp.
- transaction/payment/wallet/payout marketplace riêng.

### Không port

- company/project/team scope.
- CRM reservation/opportunity.
- Accounting, Reports, HR/Payroll.
- Product hierarchy khi chưa có consumer.
- quantity/inventory hàng hóa số lượng lớn.
- full ProductInventoryService của cha.

## 5. Quy trình bắt buộc trước mọi task

1. Xác định ZIP develop Mini mới nhất và AXIRO cha mới nhất.
2. Giải nén riêng; không unzip chồng để phân tích.
3. Tìm owner source cha, import, CSS, utilities, hooks, consumers và tests.
4. Lập dependency graph.
5. Phân loại `exact_source`, `mechanical_conversion`, `bounded_adapter`, `mini_bounded`, `deferred`, `excluded`.
6. Kiểm tra nghiệp vụ Mini có consumer thật.
7. Chỉ sau đó mới sửa code.
8. Rà toàn bộ phạm vi ảnh hưởng.
9. Format tất cả file thay đổi.
10. Chạy source closure, lint/build, targeted tests và full tests khả dụng.
11. Chỉ đóng ZIP sau khi build/test pass hoặc ghi rõ blocker có bằng chứng.

## 6. Phạm vi ảnh hưởng phải rà

Mỗi thay đổi schema/lifecycle phải kiểm tra tối thiểu:

- migration gốc;
- model, enum, cast, relation, accessor;
- request validation và normalize;
- resource/transformer/API snapshot;
- service và transaction boundary;
- controller/routes;
- factory;
- seeder/demo data;
- integrity command;
- unit/feature/MySQL concurrency tests;
- Admin form/list/filter/action/detail;
- Storefront route/form/card/detail/action;
- notification/timeline/audit;
- document/template variables;
- CSS/base/hooks;
- docs/ADR/runbook.

## 7. Chính sách migration cho dự án đang phát triển

Baseline hiện tại được phép sửa migration gốc.

- Không tạo migration chuyển đổi nếu người dùng đã xác nhận có thể `migrate:fresh`.
- Không thêm cột tạm rồi drop ngay trong migration sau.
- Không giữ song song schema cũ và mới.
- Seeder phải chạy đúng một lần và idempotent.
- Đường chạy chuẩn: `migrate:fresh --seed`.
- Migration compatibility chỉ tồn tại khi có yêu cầu bảo toàn dữ liệu cụ thể.

## 8. Gate bắt buộc trước bàn giao

### Admin

```bash
npm run format
npm run format:check
npm run check:source-closure
npm run check:renderer-closure
npm run check:base-consumer-adoption
npm run check:all
npm run lint
npm run build
```

### API

```bash
./vendor/bin/pint <changed-files>
./vendor/bin/pint --test <changed-files>
php -l <changed-files>
php artisan migrate:fresh --seed
php artisan test --filter=<targeted>
php artisan marketplace:integrity
php artisan test
```

### MBN React

```bash
npm run format
npm run format:check
npm run check:base-first
npm run check:api-contract
npm run check:all
npm run lint
npm run build
```

## 9. Các lỗi tuyệt đối không được lặp lại

- Export barrel trỏ tới file chưa đóng gói.
- Import alias không resolve.
- Copy component cha nhưng bỏ sót CSS/import side-effect.
- Tự chế CSS/component chỉ vì cùng tên.
- Tạo migration vá sau khi đã được phép sửa migration gốc.
- Dùng status chung cho approval/publication/availability.
- Coi installment là offer mode ngang cấp sell/rent.
- Copy full service cha có company/RBAC/CRM vào Mini.
- Chỉ chạy source gate mà không chạy build thật.
- Tuyên bố test pass khi chưa chạy.

## 10. Handoff bắt buộc

Mỗi lần bàn giao phải có:

- full 3 ZIP;
- MD report;
- DOCX report khi là thay đổi kiến trúc lớn;
- danh sách file thêm/sửa/xóa;
- phần copy nguyên từ cha và hash;
- phần adapter và lý do;
- phần không port;
- migration/data impact;
- test đã chạy thật;
- test chưa chạy và lý do;
- lệnh áp dụng từ `~/Downloads`;
- lệnh format, test, commit, push và `git archive`.

## 11. Stop conditions

Không được tiếp tục code hoặc đóng ZIP nếu:

- chưa xác định source owner ở AXIRO cha;
- chưa đọc imports/CSS/consumer của source cha;
- dependency local bị thiếu;
- có unresolved import/export;
- migration fresh/seed fail;
- lint/build fail;
- lifecycle tài chính chưa có transaction/idempotency;
- chưa rà factory/seeder/demo/test;
- chưa xác định source of truth giữa các entity.

## 12. Quyết định hiện tại cho Product Mini

Canonical định hướng hiện tại:

```text
Product
├── game_code
├── product_type
├── offer_modes: sell/rent
├── installment capability
├── sale/rental terms
├── approval_status
├── is_published
├── availability_status/version
├── hold/history
└── Transaction snapshot
```

`ProductListing` không còn là owner nghiệp vụ. `Contract` vẫn giữ tạm thời đến khi có quyết định riêng.

Trước khi mở rộng Product lần tiếp theo, phải đối chiếu lại các source cha nêu ở Mục 2 và cập nhật ma trận tích hợp nếu AXIRO cha thay đổi.
