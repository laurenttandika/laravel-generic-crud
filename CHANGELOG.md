# Changelog

All notable changes to this project will be documented in this file.

---

## [0.1.0] - 2025-09-14
### Added
- Initial release of **Qnox Laravel Generic CRUD** 🎉
- `Route::crud()` macro for quick RESTful route registration with policy checks and Sanctum auth.
- `php artisan make:crud` command:
  - Generates Model, Migration, Controller, FormRequest, Policy, Resource, Factory, Seeder, Views, and Tests from one command.
  - Supports JSON schema (`--schema=...`) or inline `--fields` definition.
  - Options for `--tenant`, `--policy`, `--api`, `--views`, `--softdeletes`.
- **Relations support**:
  - `belongsTo`: adds foreign key, validation rule, model method, and resource include.
  - `hasMany`: adds model method and resource include comment.
- **Nested routes support**:
  - Define dotted URIs like `posts.comments` → `/posts/{post}/comments`.
  - Child controllers can implement `parentConfig()` to auto-scope queries and auto-fill FKs on store.
- Multi-tenant support with `HasTenantScope` trait (`tenant_id` auto-filled and globally scoped).
- Base `CrudController` with:
  - Searchable & sortable columns
  - Filter DSL (`?filter[field][op]=value`)
  - Pagination with query string persistence
- **Bulk actions** endpoint: delete, restore, forceDelete, and `update:{key:value}`.
- **CSV export** endpoint with configurable chunk size, delimiter, and columns.
- **Audit trail** with `Auditable` trait:
  - Auto-fills `created_by` / `updated_by`.
  - Optional changes log table hook.
- Stubs for model, migration, controller, request, policy, resource, factory, seeder, and Blade views.
- Configurable defaults via `config/crud.php`.
- Expanded README with JSON schema format, relations examples, and nested route usage.

---

[0.1.0]: https://github.com/laurenttandika/laravel-generic-crud/releases/tag/v0.1.0