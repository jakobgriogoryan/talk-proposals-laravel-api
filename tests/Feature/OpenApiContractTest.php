<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\FileConstants;
use App\Constants\PaginationConstants;
use App\Constants\ValidationConstants;
use App\Enums\ProposalStatus;
use App\Enums\ReviewRating;
use App\Enums\UserRole;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use L5Swagger\GeneratorFactory;
use Tests\TestCase;

class OpenApiContractTest extends TestCase
{
    private string $docsPath;

    private array $document;

    protected function setUp(): void
    {
        parent::setUp();
        $this->docsPath = sys_get_temp_dir().'/talk-proposals-openapi-'.Str::uuid();
        config([
            'session.cookie' => 'review_session',
            'l5-swagger.defaults.paths.docs' => $this->docsPath,
            'l5-swagger.defaults.paths.base' => '/api',
        ]);
        app(GeneratorFactory::class)->make('default')->generateDocs();
        $this->document = json_decode(File::get($this->docsPath.'/api-docs.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->docsPath);
        parent::tearDown();
    }

    public function test_every_api_route_has_a_documented_operation(): void
    {
        $normalize = fn (string $path) => preg_replace('/\{[^}]+\}/', '{}', $path);
        $operations = [];
        foreach ($this->document['paths'] as $path => $methods) {
            foreach (array_keys($methods) as $method) {
                $operations[] = strtoupper($method).' '.$normalize($path);
            }
        }
        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/') || ! in_array('api', $route->middleware(), true)) {
                continue;
            }
            foreach (array_diff($route->methods(), ['HEAD', 'OPTIONS']) as $method) {
                $operation = $method.' '.$normalize(substr($route->uri(), 3));
                $this->assertContains($operation, $operations, 'Undocumented API operation: '.$operation);
            }
        }
    }

    public function test_servers_and_authentication_are_portable_and_match_runtime(): void
    {
        $this->assertSame([['url' => '/api']], $this->document['servers']);
        $schemes = $this->document['components']['securitySchemes'];
        $this->assertSame('review_session', $schemes['sanctum']['name']);
        $this->assertSame('cookie', $schemes['sanctum']['in']);
        $this->assertSame('bearer', $schemes['bearerAuth']['scheme']);
        foreach ($this->document['paths'] as $path => $methods) {
            foreach ($methods as $operation) {
                if (! isset($operation['security']) || in_array($path, ['/register', '/login', '/sanctum/csrf-cookie'], true)) {
                    continue;
                }
                $this->assertContains(['sanctum' => []], $operation['security']);
                $this->assertContains(['bearerAuth' => []], $operation['security']);
            }
        }
    }

    public function test_schemas_and_query_limits_match_domain_validation(): void
    {
        $schemas = $this->document['components']['schemas'];
        $this->assertSame(ReviewRating::values(), $schemas['Review']['properties']['rating']['enum']);
        $this->assertSame(ProposalStatus::values(), $schemas['Proposal']['properties']['status']['enum']);
        $this->assertSame(UserRole::values(), $schemas['User']['properties']['role']['enum']);
        $this->assertArrayHasKey('reviews', $schemas['Proposal']['properties']);
        $registration = $this->document['paths']['/register']['post']['requestBody']['content']['application/json']['schema']['properties'];
        $this->assertSame(UserRole::registrationRoles(), $registration['role']['enum']);
        $this->assertSame(['success', 'error'], $schemas['ApiResponse']['properties']['status']['enum']);
        foreach (['/proposals', '/review/proposals', '/admin/proposals', '/tags'] as $path) {
            $parameters = array_column($this->document['paths'][$path]['get']['parameters'], null, 'name');
            $this->assertSame(1, $parameters['page']['schema']['minimum']);
            $this->assertSame(PaginationConstants::MAX_PER_PAGE, $parameters['per_page']['schema']['maximum']);
            $this->assertSame($path === '/tags' ? PaginationConstants::DEFAULT_TAGS_PER_PAGE : PaginationConstants::DEFAULT_PER_PAGE, $parameters['per_page']['schema']['default']);
            $this->assertSame(ValidationConstants::MAX_SEARCH_LENGTH, $parameters['search']['schema']['maxLength']);
            $this->assertArrayHasKey('422', $this->document['paths'][$path]['get']['responses']);
        }
        $reviewParameters = array_column($this->document['paths']['/proposals/{proposalId}/reviews']['get']['parameters'], null, 'name');
        $this->assertSame(PaginationConstants::DEFAULT_REVIEWS_PER_PAGE, $reviewParameters['per_page']['schema']['default']);
        $this->assertSame(PaginationConstants::MAX_REVIEWS_PER_PAGE, $reviewParameters['per_page']['schema']['maximum']);
    }

    public function test_multipart_updates_document_method_override_and_empty_tags(): void
    {
        $body = $this->document['components']['requestBodies']['ProposalUpdate']['content']['multipart/form-data']['schema'];
        $this->assertArrayHasKey('_method', $body['properties']);
        $this->assertSame(['PUT', 'PATCH'], $body['properties']['_method']['enum']);
        $this->assertArrayHasKey('oneOf', $body['properties']['tags']);
        $this->assertSame('[]', $body['properties']['tags']['oneOf'][1]['enum'][0]);
        $this->assertSame(FileConstants::MAX_FILE_SIZE_BYTES, $body['properties']['file']['x-max-size-bytes']);
        $this->assertSame(ValidationConstants::MAX_TITLE_LENGTH, $body['properties']['title']['maxLength']);
        $csrf = $this->document['paths']['/sanctum/csrf-cookie']['get'];
        $this->assertSame([['url' => '/']], $csrf['servers']);
        $this->assertArrayHasKey('204', $csrf['responses']);
    }
}
