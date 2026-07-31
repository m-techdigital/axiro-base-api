# AXIRO Base File Manifest

| Source area | Destination | Action | Reason |
|---|---|---|---|
| `app/Http/Controllers/AuthController.php` | same path | Modify | Remove permissions, profile, security center and password-reset dependencies |
| `app/Services/Auth/AuthService.php` | same path | Modify | Keep JWT login/refresh/logout only |
| `app/Models/User.php` | same path | Modify | Minimal single-admin identity |
| Product model/controller/requests | same architectural paths | Modify | Canonical minimal product CRUD |
| Transaction model/controller/requests | same architectural paths | Modify | Remove CRM/accounting/inventory dependencies; retain money lifecycle |
| Contract model/controller/requests | same architectural paths | Modify | One contract per transaction; remove legal/accounting extensions |
| Original migrations | `database/migrations/*` | Replace | Clean canonical schema without legacy parallel tables |
| `src/services/axios.js` | same path | Modify | Retain access-token interceptor and refresh queue |
| `src/services/base.service.js` | same path | Keep/trim | Shared CRUD factory |
| `src/hooks/useAuth.jsx` | same path | Modify | Minimal current-user session |
| `src/hooks/useList.js` | same path | Extract | Shared pagination/list base hook |
| `src/hooks/useDetail.js` | same path | Extract | Shared detail base hook |
| `src/hooks/useRelationOptions.jsx` | same path | Extract | Relation labels instead of raw IDs |
| Product/Transaction/Contract FE modules | same module paths | Modify | Minimal list/create/edit flows |
| All unrelated modules | omitted | Delete | Outside base scope and dependency graph |
