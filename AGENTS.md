# Laravel API Guidance

## Stack and Purpose

- Laravel 12 API on PHP 8.2+ with Sanctum, Scout, broadcasting/Pusher, PHPUnit 11, and Pint.
- Implements authentication, role-aware proposal and review workflows, file downloads, search, and realtime events.

## Commands

- Setup: `composer install`
- Development: `composer dev`
- Focused test: `php artisan test --filter <TestName>`
- Full tests: `composer test`
- Format check: `vendor/bin/pint --test`

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
- Run focused tests, then `composer test`, `vendor/bin/pint --test`, and `git diff --check`.
- Inspect the repository diff and status before committing.
