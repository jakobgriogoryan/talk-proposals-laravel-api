<?php

// Credentials stay on the server. TLS verification is never disabled.
$connection = [
    'hosts' => [env('ELASTIC_HOST', 'http://127.0.0.1:9200')],
    'retries' => 1,
    'httpClientOptions' => ['connect_timeout' => 2, 'timeout' => 3],
];

if (env('ELASTIC_API_KEY')) {
    $connection['apiKey'] = [env('ELASTIC_API_KEY')];
} elseif (env('ELASTIC_USERNAME')) {
    $connection['basicAuthentication'] = [env('ELASTIC_USERNAME'), env('ELASTIC_PASSWORD', '')];
}

if (env('ELASTIC_CA_BUNDLE')) {
    $connection['caBundle'] = env('ELASTIC_CA_BUNDLE');
}

return ['default' => 'default', 'connections' => ['default' => $connection]];
