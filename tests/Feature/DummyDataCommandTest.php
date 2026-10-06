<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DummyDataCommandTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('nonDevelopmentEnvironments')]
    public function test_dummy_data_is_rejected_before_writing_outside_development(string $environment): void
    {
        Queue::fake();
        $this->app['env'] = $environment;
        $this->artisan('app:seed-dummy-data')->assertExitCode(Command::FAILURE);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('proposals', 0);
        $this->assertDatabaseCount('tags', 0);
    }

    public static function nonDevelopmentEnvironments(): array
    {
        return [['production'], ['staging']];
    }

    public function test_dummy_data_still_works_in_development(): void
    {
        Queue::fake();
        $this->artisan('app:seed-dummy-data')->assertExitCode(Command::SUCCESS);
        $this->assertDatabaseCount('users', 9);
        $this->assertDatabaseCount('proposals', 20);
        $this->assertDatabaseCount('tags', 10);
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $this->assertSame(UserRole::ADMIN, $admin->role);
        $this->assertTrue(Hash::check('password', $admin->password));
    }
}
