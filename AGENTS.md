# Laravel API Guidance

## Stack and Purpose

- Laravel 12 API on PHP 8.2+ with Sanctum, Scout, broadcasting/Pusher, PHPUnit 11, and Pint.
- Implements authentication, role-aware proposal and review workflows, file downloads, search, and realtime events.
- Database title search is the default; optional Algolia or Elasticsearch 8 search uses Scout. See `ELASTICSEARCH_SETUP.md` for engine switching and the loopback-only local `compose.search.yml` service.

## Commands

- Setup: `composer install`
- Development: `composer dev`
- Focused test: `php artisan test --filter <TestName>`
- Full tests: `composer test`
- Optional live Elasticsearch tests: `php artisan test tests/Integration/ElasticsearchIntegrationTest.php` (requires the local search service; isolated test DB and disposable indices).
- Format check: `vendor/bin/pint --test`
- Static analysis: `composer analyse` (Larastan/PHPStan level 5; no suppression baseline).
- Swagger browser-hook tests: `composer test:swagger` (Node 22).

## Architecture and Boundaries

- Define routes in `routes/api.php` and broadcast authorization in `routes/channels.php`.
- Use Form Requests for request validation and Policies for authorization.
- Keep API responses consistent with `App\Helpers\ApiResponse` where repository behavior requires it.
- Preserve speaker ownership, reviewer access, and admin-only status/review management.
- Nested review routes must prove the review belongs to the route proposal.
- Broadcast payloads must be safe for every subscriber to every channel returned by the event.

## Change Rules

- Preserve Sanctum SPA cookie authentication and legitimate token authentication.
- Do not weaken CSRF, authentication, policy, validation, or file-access boundaries.
- Do not log or commit credentials; tracked examples must use unmistakable placeholders.
- Avoid unrelated schema, Redis, TypeScript, or product-scope changes.

## Verification

- Add focused feature/unit coverage for observable authorization and event contracts.
- Run focused tests, then `composer test`, `composer test:swagger`, `composer analyse`, `vendor/bin/pint --test`, and `git diff --check`.
- `.github/workflows/ci.yml` checks PRs and main on PHP 8.2 with isolated SQLite; it does not deploy or contact live search/mail/broadcast services.
- Inspect the repository diff and status before committing.
