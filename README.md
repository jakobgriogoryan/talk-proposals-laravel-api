<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
# talk-proposals-api

## Search indexing and proposal creation

Scout indexes proposal creates, updates, status changes, and deletes on the
queue after database transactions commit. Review ratings and tag-only edits
use `IndexProposalJob`, which indexes directly on the worker and retries failures.
An Algolia or Elasticsearch outage must not prevent a proposal and its attachment from being saved.
The old submitted/status indexing listener classes remain available for any jobs
already queued before deployment, but are no longer registered for new events.

When using Algolia or Elasticsearch, keep `SCOUT_QUEUE=true` and use an asynchronous connection
such as `QUEUE_CONNECTION=database`, not `sync`. Scout waits for commits by default;
rolled-back proposals are not indexed. Do not disable queuing with an external
search engine, as that puts network calls back into HTTP requests.

After changing configuration or deploying these changes:

```sh
php artisan config:clear
php artisan event:clear
php artisan queue:restart
php artisan queue:work --tries=3 --backoff=5 --timeout=60
```

Keep the worker running in a separate terminal or under a process supervisor.
The retry options apply to Scout's native jobs; the review/tag indexing job also
has three attempts and a five-second backoff. Indexing is eventually consistent.
If Algolia credentials/connectivity remain invalid, jobs will fail independently
of proposal saving. Correct the Algolia configuration before retrying failed jobs
with `php artisan queue:retry <job-id>`; inspect them with `php artisan queue:failed`.
To rebuild a stale index after fixing connectivity, use
`php artisan scout:import 'App\Models\Proposal'`. For local development without Algolia, use
`SCOUT_DRIVER=collection` instead.

Local development defaults to `collection` and uses database title search.
Algolia example credentials do not activate remote requests or indexing. To
enable Algolia later, set real server-side credentials and `SCOUT_DRIVER=algolia`,
clear configuration, restart the queue worker, sync settings with
`php artisan scout:sync-index-settings`, and run `php artisan scout:check-algolia`.
The check is read-only and validates connection, permissions and filter settings
without exposing credentials. Then queue a fresh `scout:import-proposals` import;
its output reports queued work, not completed indexing. See
[SCOUT_SEARCH_SETUP.md](SCOUT_SEARCH_SETUP.md) for the full workflow.
Custom indexing jobs for proposals deleted before the worker runs are discarded
instead of retried as failures.

## Optional Elasticsearch alternative

Elasticsearch 8 is available through `SCOUT_DRIVER=elastic`; local database search
remains the default. The same proposal/reviewer/admin endpoints preserve their
permissions and filters for all engines. See [Elasticsearch setup](ELASTICSEARCH_SETUP.md)
for the optional loopback-only Docker service, safe mapping setup, verification,
queue/import workflow, authenticated remote configuration, and engine switching.

## Proposal workflow guarantees

- Attachment replacements validate PDF structure and the proposal owner's quota before changing the record. The old file is removed after commit; a rollback removes only the new upload.
- File-processing jobs ignore superseded attachments. Validation rejections clear the matching record and its cache; temporary processing failures retain the file for retry. Stored files are not counted twice against quota.
- Editing review ratings queues a search-index refresh without sending a new-review notification. Proposal changes invalidate every supported top-rated limit (1–50).
- Algolia filters use Scout's search options. Algolia failures fall back to database **title** search with the same speaker ownership, status, tags, and pagination constraints; unrelated exceptions are not masked by this fallback.
- Multipart updates can send `tags=\"[]\"` (the literal string `[]`) to remove every tag. An absent field leaves tags unchanged; nonempty arrays continue to use `tags[]`.

Regression coverage: `php artisan test --filter='WorkflowRegressionTest|ProcessProposalFileJobTest'`.
