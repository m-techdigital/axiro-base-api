# Parent offer mode alignment

Mini dùng cùng khái niệm canonical với AXIRO cha:

- `product_type`: loại bản thân sản phẩm.
- `offer_modes`: quan hệ nhiều giá trị `sell`/`rent`.
- `installment_enabled`: capability riêng của mục đích bán.
- `approval_status` tách khỏi `is_published`.
- Availability thay đổi qua service có row lock, version, hold và history.

Mini không port company/project/RBAC, reservation và CRM allocation graph của AXIRO cha.
