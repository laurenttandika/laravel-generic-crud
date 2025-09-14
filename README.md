# Qnox Laravel Generic CRUD

Schema-driven CRUD scaffolder and runtime helpers for Laravel. Includes:
- `Route::crud()` macro with policies & Sanctum
- `php artisan make:crud` (Model, Migration, Controller, FormRequest, Policy, Resource, Factory, Seeder, Views, Tests)
- Multi-tenant scoping helper
- Bulk actions (bulk delete/update), soft delete safety
- CSV export for any index result
- Query Filters DSL: `?filter[status]=active&filter[price][between]=100,200&sort=-created_at`
- Audit trail hooks (`created_by`, `updated_by`) + changes log
- Optional JSON schema input to define fields once

## Install (local path dev)
```bash
composer config repositories.qnox-crud path ./packages/qnox/laravel-generic-crud
composer require qnox/laravel-generic-crud:dev-main
php artisan vendor:publish --tag=crud-config
php artisan vendor:publish --tag=crud-stubs
```

## Quick Start
```php
// routes/api.php
use App\Http\Controllers\PostController;
Route::crud('posts', PostController::class);
```

```bash
php artisan make:crud Post \
  --fields="title:string,slug:string:unique,body:text,published_at:datetime,null" \
  --tenant --api --views --policy --softdeletes
```

## Extras
- **Bulk**: `POST /posts/bulk` with payload `{ action: "delete", ids: [1,2,3] }`
- **Export**: `GET /posts/export?format=csv&q=term&filter[status]=draft`
- **Filters DSL**: `?filter[field][op]=value` ops: `eq,neq,gt,gte,lt,lte,like,between,in,not_in,is,not`
- **Audit**: add `use \Qnox\Crud\Support\Auditable;` to model or register `AuditObserver`

See `config/crud.php` and stubs.
