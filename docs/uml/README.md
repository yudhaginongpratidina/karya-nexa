# UML API Services

Folder ini berisi sequence diagram dan activity diagram dalam format PlantUML untuk service API Laravel.

## Daftar Diagram

| Service | Sequence Diagram | Activity Diagram |
| --- | --- | --- |
| Auth | `auth-sequence.puml` | `auth-activity.puml` |
| User | `user-sequence.puml` | `user-activity.puml` |
| Period | `period-sequence.puml` | `period-activity.puml` |
| Category | `category-sequence.puml` | `category-activity.puml` |
| Criteria | `criteria-sequence.puml` | `criteria-activity.puml` |
| Performance | `performance-sequence.puml` | `performance-activity.puml` |
| TOPSIS | `topsis-sequence.puml` | `topsis-activity.puml` |

## Cakupan Alur

Diagram dibuat berdasarkan `routes/api.php` dan controller di `app/Http/Controllers/Api`.
Setiap diagram mencakup request dari client/user, routing Laravel, middleware `auth:sanctum`, `RoleMiddleware`, controller, validasi, model/database, dan response sukses maupun error utama.
