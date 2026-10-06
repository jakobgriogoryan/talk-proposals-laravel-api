<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use App\Helpers\AlgoliaConfiguration;
use App\Models\Proposal;
use Illuminate\Console\Command;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\Algolia4Engine;

class CheckAlgolia extends Command
{
    protected $signature = 'scout:check-algolia';

    protected $description = 'Check Algolia credentials, permissions and proposal filters without modifying remote data';

    public function handle(EngineManager $manager): int
    {
        if (! AlgoliaConfiguration::isConfigured()) {
            $this->error('Algolia credentials are missing, placeholders, or malformed. Set real ALGOLIA_APP_ID and ALGOLIA_SECRET privately in .env.');

            return self::FAILURE;
        }
        if (config('scout.driver') !== 'algolia') {
            $this->error('Algolia is not active. Set SCOUT_DRIVER=algolia and clear the configuration cache before checking it.');

            return self::FAILURE;
        }
        if (! config('scout.queue') || config('queue.default') === 'sync') {
            $this->error('Algolia requires SCOUT_QUEUE=true and an asynchronous queue connection.');

            return self::FAILURE;
        }

        try {
            // Scout forwards these client methods through its configured engine.
            $client = $manager->engine('algolia');
            if (! $client instanceof Algolia4Engine) {
                $this->error('Algolia diagnostics require the configured Scout Algolia v4 engine.');

                return self::FAILURE;
            }
            $key = $client->getApiKey(config('scout.algolia.secret'));
            $missing = array_diff(['search', 'addObject', 'deleteObject', 'settings', 'editSettings'], $key['acl'] ?? []);
            if ($missing) {
                $this->error('Server API key is missing required permissions: '.implode(', ', $missing).'.');

                return self::FAILURE;
            }

            $index = (new Proposal)->indexableAs();
            $settings = $client->getSettings($index);
            $facets = $settings['attributesForFaceting'] ?? [];
            foreach (['user_id', 'status', 'tag_ids'] as $attribute) {
                if (! array_intersect([$attribute, "filterOnly({$attribute})", "searchable({$attribute})"], $facets)) {
                    $this->error('Proposal filters are not configured. Run php artisan scout:sync-index-settings, then check again.');

                    return self::FAILURE;
                }
            }
            $client->searchSingleIndex($index, [
                'query' => '', 'hitsPerPage' => 0, 'analytics' => false,
                'filters' => 'user_id:-1 AND status:pending AND tag_ids:-1',
            ]);
        } catch (AlgoliaException $exception) {
            // Exception messages/requests may include application or key details.
            $message = match ((int) $exception->getCode()) {
                401, 403 => 'Algolia rejected the credentials or index permissions.',
                404 => 'Algolia application, API key or proposal index was not found. Check credentials and sync index settings.',
                default => 'Algolia request failed. Check connectivity, DNS/TLS and the application ID.',
            };
            $this->error($message);

            return self::FAILURE;
        }

        $this->info('Algolia connection, server permissions and proposal filters verified (read-only).');
        $this->info('Keep the queue worker running; an import queues indexing, it does not prove jobs completed.');

        return self::SUCCESS;
    }
}
