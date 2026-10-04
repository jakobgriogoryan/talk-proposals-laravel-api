<?php

declare(strict_types=1);

namespace App\Helpers;

final class AlgoliaConfiguration
{
    public static function isConfigured(): bool
    {
        $id = (string) config('scout.algolia.id', '');
        $key = (string) config('scout.algolia.secret', '');

        // Reject copied example values without imposing a format on scoped keys.
        return preg_match('/^[a-z0-9]+$/iD', $id) === 1
            && ! in_array(strtolower($id), ['placeholder', 'changeme', 'example', 'yourappid', 'yourapplicationid'], true)
            && $key !== ''
            && preg_match('/\s/', $key) === 0
            && preg_match('/^(your[_-]|example[_-]|placeholder|changeme)/i', $key) === 0;
    }
}
