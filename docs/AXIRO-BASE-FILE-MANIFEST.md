# AXIRO Base File Manifest

| Source area | Destination | Action | Reason |
|---|---|---|---|
| `app/Http/Controllers/AuthController.php` | same path | Modify | Remove permissions, profile, security center and password-reset dependencies |
| `app/Services/Auth/AuthService.php` | same path | Modify | Keep JWT login/refresh/logout only |
| `app/Models/User.php` | same path | Modify | Minimal single-admin identity |
| Product model/controller/requests | same architectural paths | Modify | Canonical minimal product CRUD |
| Transaction model/controller/requests | same architectural paths | Modify | Remove CRM/accounting/inventory dependencies; retain money lifecycle |
| Generated document model/controller/services | same architectural paths | Modify | Transaction-owned document artifacts; no standalone Contract module |
| Escrow Box model/controller/requests/services | Mini marketplace paths | Add | Private one-time invite escrow flow for game accounts/items |
| Original migrations | `database/migrations/*` | Replace | Clean canonical schema without legacy parallel tables |
| Product/Transaction/Document MBN surfaces | customer route paths | Modify | Customer marketplace flows only |
| All unrelated modules | omitted | Delete | Outside base scope and dependency graph |
