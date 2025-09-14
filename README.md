# Qnox Laravel Generic CRUD

Schema-driven CRUD scaffolder and runtime helpers for Laravel. Includes:
- `Route::crud()` macro with policies & Sanctum
- `php artisan make:crud` (Model, Migration, Controller, FormRequest, Policy, Resource, Factory, Seeder, Views, Tests)
- Multi-tenant scoping helper
- Bulk actions (bulk delete/update) and soft-delete safety
- CSV export for any index result
- Query Filters DSL: `?filter[status]=active&filter[price][between]=100,200&sort=-created_at`
- Audit trail hooks (`created_by`, `updated_by`)
- **Relations support** (`belongsTo`, `hasMany`) via schema JSON

## Install (local dev)
```bash
composer require qnox/laravel-generic-crud:^0.1
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
  --schema=schema/posts.json \
  --tenant --api --views --policy --softdeletes
```

## Schema with Relations
`schema/posts.json`
```json
{
  "name": "Post",
  "table": "posts",
  "fields": [
    { "name": "title", "type": "string", "rules": "required" },
    { "name": "body", "type": "text", "rules": "required" },
    { "name": "user_id", "type": "foreignId", "rules": "required|exists:users,id" }
  ],
  "relations": [
    { "type": "belongsTo", "name":"user", "target": "App\\Models\\User", "field": "user_id", "table":"users", "onDelete":"cascade" },
    { "type": "hasMany", "name":"comments", "target": "App\\Models\\Comment", "foreign_key":"post_id" }
  ],
  "searchable": ["title","body"],
  "softDeletes": true
}
```

### What relations support does
- Adds foreign key columns & constraints for `belongsTo` if missing
- Adds model relation methods
- Adds `exists:table,id` validation rule
- Resource includes FK + commented `whenLoaded()` examples


## Nested Routes (NEW)
You can define nested CRUD routes using dotted URIs:
```php
// e.g., /posts/{post}/comments
Route::crud('posts.comments', CommentController::class);
```

In your child controller, declare how to scope to the parent with `parentConfig()`:
```php
class CommentController extends CrudController
{
    public static function model(): string { return Comment::class; }
    protected function resource(): string { return CommentResource::class; }
    protected function requestClass(): string { return CommentRequest::class; }

    // This tells the base controller how to find the parent from the route
    protected function parentConfig(): array
    {
        return [
            'param' => 'post',     // route param name from /posts/{post}
            'fk'    => 'post_id',  // child's FK
            'model' => \App\Models\Post::class,
        ];
    }
}
```
Now `index` only lists comments for that post, and `store` auto-fills `post_id` from the URL param.
