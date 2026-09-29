<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BroadcastAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher.key' => 'test-key',
            'broadcasting.connections.pusher.secret' => 'test-secret',
            'broadcasting.connections.pusher.app_id' => 'test-app',
            'broadcasting.connections.pusher.options.cluster' => 'mt1',
        ]);

        $this->app->make(BroadcastManager::class)->forgetDrivers();
        require base_path('routes/channels.php');
    }

    public function test_shared_proposals_channel_rejects_speakers(): void
    {
        $speaker = User::factory()->create(['role' => UserRole::SPEAKER->value]);

        $this->authorizeChannel($speaker, 'private-proposals')->assertForbidden();
    }

    public function test_shared_proposals_channel_allows_reviewers_and_admins(): void
    {
        foreach ([UserRole::REVIEWER, UserRole::ADMIN] as $role) {
            $user = User::factory()->create(['role' => $role->value]);

            $this->authorizeChannel($user, 'private-proposals')->assertOk();
        }
    }

    public function test_proposal_channel_enforces_proposal_visibility(): void
    {
        $owner = User::factory()->create(['role' => UserRole::SPEAKER->value]);
        $otherSpeaker = User::factory()->create(['role' => UserRole::SPEAKER->value]);
        $reviewer = User::factory()->create(['role' => UserRole::REVIEWER->value]);
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $proposal = Proposal::factory()->create(['user_id' => $owner->id]);
        $channel = "private-proposals.{$proposal->id}";

        $this->authorizeChannel($owner, $channel)->assertOk();
        $this->authorizeChannel($reviewer, $channel)->assertOk();
        $this->authorizeChannel($admin, $channel)->assertOk();
        $this->authorizeChannel($otherSpeaker, $channel)->assertForbidden();
    }

    public function test_user_channel_only_allows_its_owner(): void
    {
        $owner = User::factory()->create(['role' => UserRole::SPEAKER->value]);
        $otherUser = User::factory()->create(['role' => UserRole::SPEAKER->value]);
        $channel = "private-user.{$owner->id}";

        $this->authorizeChannel($owner, $channel)->assertOk();
        $this->authorizeChannel($otherUser, $channel)->assertForbidden();
    }

    public function test_broadcast_authentication_requires_authentication(): void
    {
        $this->postJson('/api/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-proposals',
        ])->assertUnauthorized();
    }

    private function authorizeChannel(User $user, string $channel)
    {
        return $this->actingAs($user)->postJson('/api/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => $channel,
        ]);
    }
}
