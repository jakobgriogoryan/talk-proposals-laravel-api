# Optional Elasticsearch 8 search

Database title search remains the default (`SCOUT_DRIVER=collection`). Algolia
and Elasticsearch are alternatives selected by the backend `.env`. Both use the
same authenticated proposal endpoints and frontend filters. No search credentials
or direct Elasticsearch connection belong in the frontend.

## Optional local service

From the API repository:

```sh
composer install
docker compose -f compose.search.yml up -d
docker compose -f compose.search.yml ps
```

Wait for `healthy`. This pins Elasticsearch 8.19.22, uses persistent index storage,
and limits memory to 1 GB. Port 9200 is bound to **127.0.0.1 only**. Authentication
is disabled in this development-only container. Do not expose or deploy this
Compose file publicly, and do not use it as production configuration.
Automatic index creation is disabled: use the setup command below before importing.

## Prepare the index before activating search

Set these backend `.env` values, leaving `SCOUT_DRIVER=collection` initially:

```dotenv
ELASTIC_HOST=http://127.0.0.1:9200
ELASTIC_USERNAME=
ELASTIC_PASSWORD=
ELASTIC_API_KEY=
ELASTIC_CA_BUNDLE=
SCOUT_QUEUE=true
QUEUE_CONNECTION=database
```

An optional `SCOUT_PREFIX` must produce a valid lowercase Elasticsearch index
name. The index is `<SCOUT_PREFIX>proposals`. Then run:

```sh
php artisan config:clear
php artisan scout:setup-elastic
php artisan scout:check-elastic
```

Setup creates explicit mappings: keyword status, numeric owner/tag IDs, typed
dates and ratings. It does **not** delete, flush, or replace existing indices.
Incompatible mappings fail safely: choose a new `SCOUT_PREFIX`, set up that index,
and import. The read-only check verifies server version, mappings and search;
it does not prove write permissions or completed indexing.

## Activate and index

Change the backend `.env` to `SCOUT_DRIVER=elastic`, then:

```sh
php artisan config:clear
php artisan queue:restart
```

Keep a worker running in another terminal (restart if not supervised):

```sh
php artisan queue:work --tries=3 --backoff=5 --timeout=60
```

Queue the initial import:

```sh
php artisan scout:import-proposals
php artisan queue:failed
```

Import reports **queued** work, not completed indexing. Verify successful worker
processing and search a known proposal before relying on the index. Do not use
`QUEUE_CONNECTION=sync` or disable Scout queuing with remote search: proposal
saves must not depend on Elasticsearch availability.

## Search behavior and engine switching

- Elasticsearch searches title, description, tags and author name. Input is plain
  text, not Lucene operators or arbitrary field selectors. Email is not searchable.
- Speaker ownership, reviewer access and admin user/status filters are preserved.
  Multiple selected tags match **any** tag. Search order follows relevance.
- Database hydration rechecks ownership/status/tags. Stale index entries cannot
  return disallowed records. Pagination totals come from the index and can lag
  changes until indexing completes. Only the requested page is hydrated.
- Transport/authentication/missing-index/service failures fall back to database
  **title** search with the same filters. Programming errors are not masked.
  Pages beyond Elasticsearch's default 10,000-result window also use SQL.
- Creates, updates, status changes and deletes are indexed after commit. Review
  ratings and tag-only changes refresh the same index. Search is eventually
  consistent: wait for the worker and Elasticsearch's refresh cycle.
- To use SQL again, set `SCOUT_DRIVER=collection`, clear config and restart the
  worker. For Algolia, follow [its setup guide](SCOUT_SEARCH_SETUP.md). After
  switching to either remote engine, import again: only the active engine receives
  updates. This is not simultaneous dual indexing.

Stop without deleting index data:

```sh
docker compose -f compose.search.yml stop
```

## Secured external Elasticsearch 8

Use an HTTPS `ELASTIC_HOST` with `ELASTIC_USERNAME`/`ELASTIC_PASSWORD` or an encoded
`ELASTIC_API_KEY` (takes precedence). Set `ELASTIC_CA_BUNDLE` to a trusted CA
certificate path for private/self-signed certificates. TLS verification remains
enabled. Never commit credentials. Scope permissions to the proposal index/prefix:
setup needs creation/mapping access, reads need search/mapping access, and workers
need indexing/deletion access.

The client permits at most one transport retry and bounds connection/request
timeouts to 2/3 seconds per attempt. Worker jobs also perform configured retries. This driver/client targets
Elasticsearch 8; Elasticsearch 9 and OpenSearch are not supported here.

## Tests

Normal CI requires no Elasticsearch service:

```sh
php artisan test --filter=ElasticsearchSearchTest
composer test
```

Run live integration tests explicitly with the local service running:

```sh
php artisan test tests/Integration/ElasticsearchIntegrationTest.php
```

These use an in-memory test database and unique disposable Elasticsearch indices.
Only the test-created indices are deleted afterwards, not the application's index.
Coverage includes full-text search, roles, filters, pagination, native bulk
indexing/deletion, ratings/tags/status refreshes, and connection-failure fallback.

References: [Scout driver 4](https://github.com/babenkoivan/elastic-scout-driver/tree/v4.0.0),
[official PHP client compatibility](https://www.elastic.co/docs/reference/elasticsearch/clients/php),
[Elastic Docker development setup](https://www.elastic.co/docs/deploy-manage/deploy/self-managed/install-elasticsearch-docker-basic).
