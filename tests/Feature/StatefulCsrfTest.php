<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StatefulCsrfTest extends TestCase
{
    use RefreshDatabase;

    public function test_stateful_spa_post_requires_a_csrf_token(): void
    {
        $user = User::factory()->create(['role' => UserRole::SPEAKER->value]);

        $response = $this->withCsrfValidationEnabled(fn () => $this
            ->actingAs($user)
            ->withHeader('Origin', 'http://localhost:5173')
            ->postJson('/api/logout'));

        $response->assertStatus(419);
    }

    public function test_stateful_spa_post_accepts_a_matching_csrf_token(): void
    {
        $user = User::factory()->create(['role' => UserRole::SPEAKER->value]);
        $token = Str::random(40);

        $response = $this->withCsrfValidationEnabled(fn () => $this
            ->actingAs($user)
            ->withSession(['_token' => $token])
            ->withHeaders([
                'Origin' => 'http://localhost:5173',
                'X-CSRF-TOKEN' => $token,
            ])
            ->postJson('/api/logout'));

        $response->assertOk();
    }

    private function withCsrfValidationEnabled(callable $request): mixed
    {
        $originalEnvironment = $this->app->environment();
        $this->app['env'] = 'local';
        config(['sanctum.stateful' => ['localhost:5173']]);

        try {
            return $request();
        } finally {
            $this->app['env'] = $originalEnvironment;
        }
    }
}
