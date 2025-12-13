<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Constants\PaginationConstants;
use App\Enums\ProposalStatus;
use App\Events\ProposalStatusChanged;
use App\Exceptions\UnauthorizedException;
use App\Helpers\ApiResponse;
use App\Helpers\CacheHelper;
use App\Http\Requests\UpdateProposalStatusRequest;
use App\Http\Resources\ProposalResource;
use App\Models\Proposal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

/**
 * Controller for admin proposal management.
 */
#[OA\Tag(name: "Admin")]
class AdminProposalController extends Controller
{
    /**
     * Display a listing of all proposals for admin.
     */
    #[OA\Get(
        path: "/admin/proposals",
        description: "Retrieves all proposals with filtering options. Only accessible by admin users. Includes additional filters like user_id.",
        summary: "List all proposals (Admin only)",
        security: [["sanctum" => []]],
        tags: ["Admin"],
        parameters: [
            new OA\Parameter(
                name: "search",
                description: "Search proposals by title",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "string", example: "Laravel")
            ),
            new OA\Parameter(
                name: "tags",
                description: "Filter by tag IDs (comma-separated)",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "string", example: "1,2,3")
            ),
            new OA\Parameter(
                name: "status",
                description: "Filter by status",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "string", enum: ["pending", "approved", "rejected"], example: "pending")
            ),
            new OA\Parameter(
                name: "user_id",
                description: "Filter by user ID",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "integer", example: 1)
            ),
            new OA\Parameter(
                name: "page",
                description: "Page number",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "integer", example: 1)
            ),
            new OA\Parameter(
                name: "per_page",
                description: "Items per page",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "integer", example: 15)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Proposals retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "message", type: "string", example: "Proposals retrieved successfully"),
                        new OA\Property(
                            property: "data",
                            properties: [
                                new OA\Property(
                                    property: "proposals",
                                    type: "array",
                                    items: new OA\Items(ref: "#/components/schemas/Proposal")
                                ),
                                new OA\Property(
                                    property: "pagination",
                                    properties: [
                                        new OA\Property(property: "current_page", type: "integer", example: 1),
                                        new OA\Property(property: "last_page", type: "integer", example: 5),
                                        new OA\Property(property: "per_page", type: "integer", example: 15),
                                        new OA\Property(property: "total", type: "integer", example: 75),
                                    ],
                                    type: "object"
                                ),
                            ],
                            type: "object"
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden - Admin only"),
            new OA\Response(response: 500, description: "Server error"),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            if (! $request->user()->isAdmin()) {
                throw new UnauthorizedException;
            }

            $perPage = min(
                max((int) $request->get('per_page', PaginationConstants::DEFAULT_PER_PAGE), PaginationConstants::MIN_PER_PAGE),
                PaginationConstants::MAX_PER_PAGE
            );

            $searchQuery = $request->filled('search') ? $request->string('search')->toString() : null;
            $useScout = $searchQuery !== null && config('scout.driver') === 'algolia' && !empty(config('scout.algolia.id'));

            // Use Scout for full-text search if available and search query is provided
            if ($useScout) {
                $proposals = $this->searchWithScout($request, $searchQuery, $perPage);
            } else {
                // Fallback to database search
                $proposals = $this->searchWithDatabase($request, $perPage);
            }

            return ApiResponse::success(
                'Proposals retrieved successfully',
                [
                    'proposals' => ProposalResource::collection($proposals->items()),
                    'pagination' => [
                        'current_page' => $proposals->currentPage(),
                        'last_page' => $proposals->lastPage(),
                        'per_page' => $proposals->perPage(),
                        'total' => $proposals->total(),
                    ],
                ]
            );
        } catch (UnauthorizedException $e) {
            return ApiResponse::error($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            Log::error('Error retrieving admin proposals', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Failed to retrieve proposals', 500);
        }
    }

    /**
     * Search proposals using Laravel Scout (Algolia) for admin.
     */
    private function searchWithScout(Request $request, string $searchQuery, int $perPage): LengthAwarePaginator
    {
        // Build Algolia filters
        $filters = [];

        // Filter by status
        if ($request->filled('status')) {
            $status = $request->string('status')->toString();
            if (in_array($status, ProposalStatus::values(), true)) {
                $filters[] = 'status:'.$status;
            }
        }

        // Filter by user
        if ($request->filled('user_id')) {
            $filters[] = 'user_id:'.(int) $request->integer('user_id');
        }

        // Filter by tags
        if ($request->filled('tags')) {
            $tagIds = is_array($request->tags) ? $request->tags : explode(',', (string) $request->tags);
            $tagIds = array_map('intval', array_filter($tagIds));
            if (count($tagIds) > 0) {
                // Algolia filter for array contains any
                $tagFilters = array_map(fn ($id) => 'tag_ids:'.$id, $tagIds);
                $filters[] = '('.implode(' OR ', $tagFilters).')';
            }
        }

        // Perform Scout search with filters
        $searchResults = Proposal::search($searchQuery)
            ->when(count($filters) > 0, function ($search) use ($filters) {
                return $search->whereRaw(implode(' AND ', $filters));
            })
            ->paginate($perPage);

        // Get the actual models from search results
        $proposalIds = $searchResults->map(fn ($result) => $result->id)->toArray();

        if (empty($proposalIds)) {
            // Return empty paginator if no results
            return new LengthAwarePaginator(
                collect([]),
                0,
                $perPage,
                1,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        }

        // Load relationships and maintain search order
        $proposals = Proposal::with(['user', 'tags', 'reviews'])
            ->whereIn('id', $proposalIds)
            ->get()
            ->sortBy(fn ($proposal) => array_search($proposal->id, $proposalIds))
            ->values();

        // Create a paginator manually to maintain Scout's pagination info
        $currentPage = $searchResults->currentPage();
        $total = $searchResults->total();

        return new LengthAwarePaginator(
            $proposals,
            $total,
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );
    }

    /**
     * Search proposals using database queries (fallback) for admin.
     */
    private function searchWithDatabase(Request $request, int $perPage): LengthAwarePaginator
    {
        $query = Proposal::with(['user', 'tags', 'reviews']);

        // Search by title (fallback to LIKE query)
        if ($request->filled('search')) {
            $query->searchByTitle($request->string('search')->toString());
        }

        // Filter by tags
        if ($request->filled('tags')) {
            $tagIds = is_array($request->tags) ? $request->tags : explode(',', (string) $request->tags);
            $tagIds = array_map('intval', array_filter($tagIds));
            if (count($tagIds) > 0) {
                $query->byTags($tagIds);
            }
        }

        // Filter by status
        if ($request->filled('status')) {
            $status = $request->string('status')->toString();
            if (in_array($status, ProposalStatus::values(), true)) {
                $query->byStatus($status);
            }
        }

        // Filter by user
        if ($request->filled('user_id')) {
            $query->byUser((int) $request->integer('user_id'));
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * Update the proposal status.
     */
    #[OA\Patch(
        path: "/admin/proposals/{id}/status",
        description: "Updates the status of a proposal. Only accessible by admin users. Triggers real-time broadcast event.",
        summary: "Update proposal status (Admin only)",
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["status"],
                properties: [
                    new OA\Property(property: "status", type: "string", enum: ["pending", "approved", "rejected"], example: "approved", description: "New proposal status"),
                ]
            )
        ),
        tags: ["Admin"],
        parameters: [
            new OA\Parameter(
                name: "id",
                description: "Proposal ID",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer", example: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Proposal status updated successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "message", type: "string", example: "Proposal status updated successfully"),
                        new OA\Property(
                            property: "data",
                            properties: [
                                new OA\Property(property: "proposal", ref: "#/components/schemas/Proposal"),
                            ],
                            type: "object"
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden - Admin only"),
            new OA\Response(response: 404, description: "Proposal not found"),
            new OA\Response(response: 422, description: "Validation error"),
            new OA\Response(response: 500, description: "Server error"),
        ]
    )]
    public function updateStatus(UpdateProposalStatusRequest $request, Proposal $proposal): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Get old status as string (status is cast to ProposalStatus enum)
            $oldStatus = $proposal->status instanceof ProposalStatus
                ? $proposal->status->value
                : (string) $proposal->status;

            $status = ProposalStatus::from($request->string('status')->toString());

            $proposal->update([
                'status' => $status->value,
            ]);

            $proposal->load(['user', 'tags']);

            DB::commit();

            // Invalidate caches related to proposals
            CacheHelper::forgetProposalRelated($proposal->id);
            CacheHelper::forgetUserRelated($proposal->user_id);

            // Broadcast proposal status changed event
            $newStatus = $status->value;
            if ($oldStatus !== $newStatus) {
                event(new ProposalStatusChanged($proposal, $oldStatus, $newStatus));
            }

            return ApiResponse::success(
                'Proposal status updated successfully',
                ['proposal' => new ProposalResource($proposal)]
            );
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error updating proposal status', [
                'proposal_id' => $proposal->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Failed to update proposal status', 500);
        }
    }
}
