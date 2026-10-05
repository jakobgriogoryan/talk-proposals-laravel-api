<?php

declare(strict_types=1);

namespace App\Documentation;

use App\Constants\FileConstants;
use App\Constants\PaginationConstants;
use App\Constants\ValidationConstants;
use App\Enums\ProposalStatus;
use App\Enums\ReviewRating;
use App\Enums\UserRole;
use OpenApi\Analysis;
use OpenApi\Annotations as OA;
use OpenApi\Generator;

/** Keep generated documentation aligned with runtime configuration and enums. */
final class RuntimeContract
{
    public function __invoke(Analysis $analysis): void
    {
        foreach ($analysis->annotations as $annotation) {
            if ($annotation instanceof OA\SecurityScheme && $annotation->securityScheme === 'sanctum') {
                $annotation->name = config('session.cookie');
            }
            if ($annotation instanceof OA\Property) {
                if ($annotation->property === 'rating') {
                    $annotation->enum = ReviewRating::values();
                } elseif ($annotation->property === 'role' && is_array($annotation->enum)) {
                    $annotation->enum = in_array(UserRole::ADMIN->value, $annotation->enum, true)
                        ? UserRole::values() : UserRole::registrationRoles();
                } elseif ($annotation->property === 'status' && is_array($annotation->enum) && in_array('pending', $annotation->enum, true)) {
                    $annotation->enum = ProposalStatus::values();
                } elseif ($annotation->property === 'title') {
                    $annotation->maxLength = ValidationConstants::MAX_TITLE_LENGTH;
                } elseif ($annotation->property === 'file') {
                    $annotation->description = 'Optional PDF, maximum '.(FileConstants::MAX_FILE_SIZE_KB / 1024).' MB.';
                    $annotation->x = ['max-size-bytes' => FileConstants::MAX_FILE_SIZE_BYTES];
                }
            }
            if ($annotation instanceof OA\Schema && $annotation->schema === 'User') {
                foreach ($annotation->properties as $property) {
                    if ($property->property === 'role') {
                        $property->enum = UserRole::values();
                    }
                }
            }
        }

        foreach ($analysis->openapi->paths as $path) {
            foreach (['get', 'post', 'put', 'patch', 'delete'] as $method) {
                $operation = $path->{$method};
                if (! $operation instanceof OA\Operation) {
                    continue;
                }
                if (is_array($operation->security) && in_array(['sanctum' => []], $operation->security, true)) {
                    $operation->security[] = ['bearerAuth' => []];
                    $this->addResponse($operation, 401, 'Unauthenticated');
                }
                $validatedQuery = false;
                foreach (is_array($operation->parameters) ? $operation->parameters : [] as $parameter) {
                    if ($parameter->in !== 'query' || ! $parameter->schema instanceof OA\Schema) {
                        continue;
                    }
                    $schema = $parameter->schema;
                    switch ($parameter->name) {
                        case 'page':
                            $schema->minimum = 1;
                            $schema->default = 1;
                            break;
                        case 'per_page':
                            $schema->default = $path->path === '/tags' ? PaginationConstants::DEFAULT_TAGS_PER_PAGE
                                : (str_contains($path->path, '/reviews') ? PaginationConstants::DEFAULT_REVIEWS_PER_PAGE : PaginationConstants::DEFAULT_PER_PAGE);
                            $schema->minimum = 1;
                            $schema->maximum = str_contains($path->path, '/reviews') ? PaginationConstants::MAX_REVIEWS_PER_PAGE : PaginationConstants::MAX_PER_PAGE;
                            break;
                        case 'limit':
                            $schema->default = PaginationConstants::DEFAULT_TOP_RATED_LIMIT;
                            $schema->minimum = 1;
                            $schema->maximum = PaginationConstants::MAX_TOP_RATED_LIMIT;
                            break;
                        case 'search':
                            $schema->maxLength = ValidationConstants::MAX_SEARCH_LENGTH;
                            break;
                        case 'user_id':
                            $schema->minimum = 1;
                            break;
                        case 'status':
                            $schema->enum = ProposalStatus::values();
                            break;
                        case 'tags':
                            $schema->type = Generator::UNDEFINED;
                            $schema->oneOf = [
                                new OA\Schema(['type' => 'string', 'example' => '1,2,3']),
                                new OA\Schema(['type' => 'array', 'items' => new OA\Items(['type' => 'integer', 'minimum' => 1])]),
                            ];
                            break;
                    }
                    $validatedQuery = true;
                }
                if ($validatedQuery) {
                    $this->addResponse($operation, 422, 'Invalid query parameters');
                }
                if ($method !== 'get') {
                    $this->addResponse($operation, 419, 'Missing or expired CSRF token for stateful SPA requests');
                }
                if (in_array($method, ['post', 'put', 'patch'], true) && ! in_array($path->path, ['/logout', '/broadcasting/auth'], true)) {
                    $this->addResponse($operation, 422, 'Invalid request data');
                }
                if (in_array($path->path, ['/login', '/register', '/proposals'], true) && $method === 'post'
                    || preg_match('#^/proposals/\{[^}]+\}$#', $path->path) && in_array($method, ['put', 'patch'], true)) {
                    $this->addResponse($operation, 429, 'Too many requests');
                }
            }
        }
    }

    private function addResponse(OA\Operation $operation, int $status, string $description): void
    {
        foreach ($operation->responses as $response) {
            if ((int) $response->response === $status) {
                return;
            }
        }
        $operation->responses[] = new OA\Response(['response' => $status, 'description' => $description]);
    }
}
