<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CorsConfigurationTest extends TestCase
{
    #[DataProvider('originSettings')]
    public function test_origins_are_explicit_and_portable(array $settings, array $expected): void
    {
        $savedEnv = $_ENV;
        $savedServer = $_SERVER;
        $savedProcess = [];
        foreach (['CORS_ALLOWED_ORIGINS', 'FRONTEND_URL'] as $key) {
            $savedProcess[$key] = getenv($key);
        }
        try {
            foreach (['CORS_ALLOWED_ORIGINS', 'FRONTEND_URL'] as $key) {
                unset($_ENV[$key], $_SERVER[$key]);
                putenv($key);
                if (isset($settings[$key])) {
                    $_ENV[$key] = $_SERVER[$key] = $settings[$key];
                    putenv($key.'='.$settings[$key]);
                }
            }
            $cors = require base_path('config/cors.php');
            $this->assertSame($expected, $cors['allowed_origins']);
            $this->assertTrue($cors['supports_credentials']);
        } finally {
            $_ENV = $savedEnv;
            $_SERVER = $savedServer;
            foreach ($savedProcess as $key => $value) {
                putenv($value === false ? $key : $key.'='.$value);
            }
        }
    }

    public static function originSettings(): array
    {
        return [
            'loopback default' => [[], ['http://localhost:5173']],
            'frontend override' => [['FRONTEND_URL' => 'https://frontend.example.test'], ['https://frontend.example.test']],
            'explicit origins' => [['CORS_ALLOWED_ORIGINS' => 'https://first.example.test, https://second.example.test, '], ['https://first.example.test', 'https://second.example.test']],
        ];
    }

    public function test_preflight_accepts_only_configured_origins(): void
    {
        config(['cors.allowed_origins' => ['https://frontend.example.test']]);
        $headers = ['Origin' => 'https://frontend.example.test', 'Access-Control-Request-Method' => 'POST', 'Access-Control-Request-Headers' => 'Content-Type,X-XSRF-TOKEN'];
        $this->options('/api/proposals', [], $headers)
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'https://frontend.example.test')
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
        $headers['Origin'] = 'https://untrusted.example.test';
        // With one configured origin, the CORS library may return that constant
        // origin even for an untrusted request. Browsers reject the mismatch.
        $response = $this->options('/api/proposals', [], $headers);
        $this->assertNotSame($headers['Origin'], $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertNotSame('*', $response->headers->get('Access-Control-Allow-Origin'));
    }
}
