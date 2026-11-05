# SmartCard Studio: notes for contributors and AI assistants

- Laravel 13 / PHP 8.3, MySQL in production, SQLite in-memory for tests.
- Build phase by phase (see README roadmap). Keep the app working after each phase.
- Business logic lives in `app/Services`; controllers stay thin; validation in Form Requests or `$request->validate()`.
- Authorize with permissions from `config/smartcard.php` (`$user->can('perm')`) and policies. Super Admin passes everything via `Gate::before`.
- Public-facing models use `HasPublicUlid` (route key `ulid`). Never expose numeric ids in URLs or API output.
- Sidebar items appear automatically when their route exists (`app/Support/Navigation.php`). Never add links to routes that do not exist.
- Blade: use `@php ... @endphp` blocks, not inline `@php(...)` (mixing them breaks compilation).
- Tailwind v4: shared component classes are `@utility` blocks in `resources/css/app.css`.
- Run `./vendor/bin/pint` and `php artisan test` before committing.
