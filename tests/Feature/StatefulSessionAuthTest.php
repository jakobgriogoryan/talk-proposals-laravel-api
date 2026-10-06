<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StatefulSessionAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable real CSRF validation and persist sessions between fresh guards.
        $this->app['env'] = 'local';
        config([
            'sanctum.stateful' => ['talkproposals.test'],
            'session.driver' => 'database',
            'session.domain' => '.talkproposals.test',
            'session.secure' => false,
        ]);
        $this->withCredentials()->withHeaders([
            'Origin' => 'http://talkproposals.test',
            'Referer' => 'http://talkproposals.test/login',
        ]);
        $this->resetRequestState();
    }

    #[DataProvider('rememberOptions')]
    public function test_login_persists_across_requests_and_logout_revokes_the_session(bool $remember): void
    {
        $user = User::factory()->create([
            'role' => UserRole::ADMIN->value,
            'password' => Hash::make('test-password'),
        ]);

        $this->loginThroughCookies($user, $remember);

        $response = $this->getJson('http://talkproposals.test/api/user');
        $response->assertOk()->assertJsonPath('data.user.id', $user->id);
        $this->carryCookies($response);

        $response = $this->getJson('http://talkproposals.test/api/admin/proposals');
        $response->assertOk();
        $this->carryCookies($response);

        $response = $this->postJson('http://talkproposals.test/api/logout');
        $response->assertOk();
        $this->carryCookies($response);

        $this->getJson('http://talkproposals.test/api/user')->assertUnauthorized();
    }

    public function test_password_changes_still_invalidate_existing_sessions(): void
    {
        $user = User::factory()->create(['password' => Hash::make('test-password')]);
        $this->loginThroughCookies($user, false);

        $response = $this->getJson('http://talkproposals.test/api/user');
        $response->assertOk()->assertJsonPath('data.user.id', $user->id);
        $this->carryCookies($response);

        $user->forceFill(['password' => Hash::make('changed-password')])->save();

        $this->getJson('http://talkproposals.test/api/user')->assertUnauthorized();
    }

    public static function rememberOptions(): array
    {
        return ['session only' => [false], 'remember me' => [true]];
    }

    private function loginThroughCookies(User $user, bool $remember): void
    {
        $response = $this->getJson('http://talkproposals.test/sanctum/csrf-cookie');
        $response->assertNoContent();
        $this->carryCookies($response);

        $response = $this->postJson('http://talkproposals.test/api/login', [
            'email' => $user->email,
            'password' => 'test-password',
            'remember' => $remember,
        ]);
        $response->assertOk();
        $this->carryCookies($response);
    }

    private function carryCookies(TestResponse $response): void
    {
        foreach ($response->headers->getCookies() as $cookie) {
            // Preserve wire-format encrypted cookies, like an actual browser.
            $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue());
            if ($cookie->getName() === 'XSRF-TOKEN') {
                $this->withHeader('X-XSRF-TOKEN', $cookie->getValue());
            }
        }

        $this->resetRequestState();
    }

    private function resetRequestState(): void
    {
        // Do not allow the test application's cached user/session to mask a
        // failure to authenticate solely from the next request's cookies.
        Auth::forgetGuards();
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
    }
}
