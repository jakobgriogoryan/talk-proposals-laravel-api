<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Requests\IndexAdminProposalRequest;
use App\Http\Requests\IndexProposalRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProposalFilterValidationTest extends TestCase
{
    use RefreshDatabase;

    public static function invalidFilters(): array
    {
        $cases = [];
        foreach (['speaker' => '/api/proposals', 'reviewer' => '/api/review/proposals', 'admin' => '/api/admin/proposals'] as $role => $url) {
            foreach (['text' => ['bad'], 'zero' => [0], 'negative' => [-1], 'fraction' => ['1.5'], 'nested' => [['id' => 1]], 'csv' => '1,bad'] as $name => $tags) {
                $cases["{$role} {$name}"] = [$role, $url, $tags];
            }
        }

        return $cases;
    }

    #[DataProvider('invalidFilters')]
    public function test_malformed_tag_ids_are_rejected_before_search(string $role, string $url, array|string $tags): void
    {
        $user = User::factory()->create(['role' => $role]);
        $this->actingAs($user, 'sanctum')->getJson($url.'?'.http_build_query(['tags' => $tags]))
            ->assertUnprocessable()->assertJsonStructure(['errors']);
    }

    public static function endpoints(): array
    {
        return [
            'speaker' => ['speaker', '/api/proposals'],
            'reviewer' => ['reviewer', '/api/review/proposals'],
            'admin' => ['admin', '/api/admin/proposals'],
        ];
    }

    #[DataProvider('endpoints')]
    public function test_valid_array_csv_and_absent_tag_filters_remain_supported(string $role, string $url): void
    {
        $user = User::factory()->create(['role' => $role]);
        foreach ([[], ['tags' => [1, 2]], ['tags' => '1,2'], ['tags' => []], ['tags' => null], ['tags' => '']] as $filters) {
            $this->actingAs($user, 'sanctum')->getJson($url.'?'.http_build_query($filters))->assertOk();
        }
    }

    #[DataProvider('endpoints')]
    public function test_list_endpoints_still_require_authentication(string $role, string $url): void
    {
        $this->getJson($url)->assertUnauthorized();
    }

    public function test_request_authorization_returns_false_without_a_user(): void
    {
        foreach ([new IndexProposalRequest, new IndexAdminProposalRequest] as $request) {
            $request->setUserResolver(fn () => null);
            $this->assertFalse($request->authorize());
        }
    }
}
