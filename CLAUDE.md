# CLAUDE.md

Guidance for Claude Code when working in this repository.

## Project

OSMS — multi-tenant B2B SaaS for optical retail. Migrated from Next.js + Supabase to
Laravel 12 + Blade + Bootstrap 5 + MySQL (Hostinger). Domains: patients/prescriptions,
barcode POS, frame/lens inventory, kanban orders, analytics, Razorpay subscriptions.

## Tech stack

- Laravel 12 (PHP 8.2+), Blade + Bootstrap 5 (SCSS compiled via Vite), Alpine.js for the order builder
- MySQL in production, SQLite locally and in tests (`:memory:`)
- Breeze auth; barryvdh/laravel-dompdf, maatwebsite/excel, simplesoftwareio/simple-qrcode, razorpay/razorpay

## Commands

```bash
php artisan test            # full suite (PHPUnit)
php artisan migrate --seed  # rebuild + demo data
npm run build               # compile Bootstrap SCSS + JS
```

On Windows the dev server runs via **Laravel Herd** (PHP/Composer live in
`C:\Users\viraj\.config\herd\bin`, not on PATH for non-interactive shells — prepend it).
`php artisan serve` may fail to bind a port here; verify via `php artisan test` instead.

## Multi-tenancy (CRITICAL)

Every store-owned model uses `App\Models\Concerns\BelongsToTenant`, which:
1. applies `App\Models\Scopes\TenantScope` (constrains all queries to `auth()->user()->tenant_id`;
   superadmins bypass), and
2. auto-stamps `tenant_id` on create.

This is the app-layer replacement for Supabase RLS. **Any new tenant-owned table/model must
use this trait** and have a `tenant_id` UUID column. Never query tenant data without it.

## Conventions

- Business tables use UUID primary keys (`HasUuid` trait). `users` stays bigint (Breeze).
- Tenant routes live in `routes/tenant.php`, included under the `tenant` prefix +
  `tenant.` name + `['auth','onboarded']` middleware in `routes/web.php`.
- Controllers: `App\Http\Controllers\Tenant\*`. Role gating via `role:` middleware.
- Money formatted as `₹ ` + `number_format(...)`. Order `balance_due` is kept in sync by the
  `Order` model's saving hook (`total_amount - advance_paid`).
- Shared chrome uses `safe_route()` (helper) so links to not-yet-built routes don't throw.
- Design tokens (deep optical blue `#004f75`, glass, card-lift, print rules) live in
  `resources/sass/app.scss`. Don't hardcode brand colors inline.

## Testing

Add a `PhaseNXxxTest` per feature. Always include a tenant-isolation assertion for new
tenant-owned data. Run `php artisan test` before considering a change done.
