<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Constants\FileConstants;
use App\Constants\PaginationConstants;
use App\Enums\ProposalStatus;
use App\Events\ProposalSubmitted;
use App\Exceptions\ProposalFileNotFoundException;
use App\Helpers\ApiResponse;
use App\Helpers\CacheHelper;
use App\Http\Requests\StoreProposalRequest;
use App\Http\Requests\UpdateProposalRequest;
use App\Http\Resources\ProposalResource;
use App\Models\Proposal;
use App\Models\Tag;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Controller for managing proposals.
 */
#[OA\Tag(name: "Proposals")]
class ProposalController extends Controller
{
    /**
     * Display a listing of proposals for reviewers.
     */
    #[OA\Get(
        path: "/review/proposals",
        description: "Retrieves all proposals for reviewers to review. Only accessible by reviewer users. Supports full-text search (using Laravel Scout with Algolia) across title, description, tags, and author name. Also supports filtering by tags and status.",
        summary: "List all proposals for review (Reviewer only)",
        security: [["sanctum" => []]],
        tags: ["Reviews"],
        parameters: [
            new OA\Parameter(
                name: "search",
                description: "Full-text search across proposal title, description, tags, and author name. Uses Laravel Scout with Algolia for advanced search capabilities when configured.",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "string", example: "Laravel framework")
            ),
            new OA\Parameter(
                name: "tags",
                description: "Filter by tag IDs (comma-separated or array)",
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
            new OA\Response(response: 403, description: "Forbidden - Reviewer only"),
            new OA\Response(response: 500, description: "Server error"),
        ]
    )]
    public function indexForReview(Request $request): JsonResponse
    {
        if (! $request->user()->isReviewer()) {
            return ApiResponse::error('Unauthorized', 403);
        }

        return $this->index($request);
    }

    /**
     * Display a listing of proposals.
     */
    #[OA\Get(
        path: "/proposals",
        description: "Retrieves a paginated list of proposals. Speakers see only their own proposals, while reviewers and admins see all proposals. Supports full-text search (using Laravel Scout with Algolia) across title, description, tags, and author name. Also supports filtering by tags and status.",
        summary: "List proposals",
        security: [["sanctum" => []]],
        tags: ["Proposals"],
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
                description: "Filter by tag IDs (comma-separated or array)",
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
            new OA\Response(response: 500, description: "Server error"),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', Proposal::class);

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
        } catch (\Exception $e) {
            Log::error('Error retrieving proposals', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Failed to retrieve proposals', 500);
        }
    }

    /**
     * Search proposals using Laravel Scout (Algolia).
     */
    private function searchWithScout(Request $request, string $searchQuery, int $perPage): LengthAwarePaginator
    {
        // Build Algolia filters
        $filters = [];

        // Filter by authenticated user if speaker
        if ($request->user()->isSpeaker() && ! $request->user()->isAdmin()) {
            $filters[] = 'user_id:'.$request->user()->id;
        }

        // Filter by status
        if ($request->filled('status')) {
            $status = $request->string('status')->toString();
            if (in_array($status, ProposalStatus::values(), true)) {
                $filters[] = 'status:'.$status;
            }
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
        $proposals = Proposal::with(['user', 'tags'])
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
     * Search proposals using database queries (fallback).
     */
    private function searchWithDatabase(Request $request, int $perPage): LengthAwarePaginator
    {
        $query = Proposal::with(['user', 'tags']);

        // Filter by authenticated user if speaker
        if ($request->user()->isSpeaker() && ! $request->user()->isAdmin()) {
            $query->byUser($request->user()->id);
        }

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

        return $query->latest()->paginate($perPage);
    }

    /**
     * Store a newly created proposal.
     */
    #[OA\Post(
        path: "/proposals",
        description: "Creates a new talk proposal. File upload is optional (PDF, max 4MB). Tags can be provided as an array of strings (will be created if they don't exist). Status defaults to 'pending'.",
        summary: "Create a new proposal",
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    required: ["title", "description"],
                    properties: [
                        new OA\Property(property: "title", description: "Proposal title (required)", type: "string", example: "Introduction to Laravel"),
                        new OA\Property(property: "description", description: "Proposal description (required)", type: "string", example: "A comprehensive guide to Laravel framework"),
                        new OA\Property(property: "file", description: "PDF file (optional, max 4MB)", type: "string", format: "binary"),
                        new OA\Property(property: "tags", description: "Array of tag names (optional)", type: "array", items: new OA\Items(type: "string"), example: ["Technology", "Laravel"]),
                    ]
                )
            )
        ),
        tags: ["Proposals"],
        responses: [
            new OA\Response(
                response: 201,
                description: "Proposal created successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "message", type: "string", example: "Proposal created successfully"),
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
            new OA\Response(response: 403, description: "Unauthorized"),
            new OA\Response(response: 422, description: "Validation error"),
            new OA\Response(response: 500, description: "Server error"),
        ]
    )]
    public function store(StoreProposalRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $filePath = null;
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $filePath = $file->store(FileConstants::PROPOSAL_STORAGE_PATH, FileConstants::PROPOSAL_STORAGE_DISK);
            }

            $proposal = Proposal::create([
                'user_id' => $request->user()->id,
                'title' => $request->string('title')->toString(),
                'description' => $request->string('description')->toString(),
                'file_path' => $filePath,
                'status' => ProposalStatus::PENDING->value,
            ]);

            // Handle tags (create if not exists, then attach) - tags are optional
            if ($request->has('tags') && is_array($request->tags) && count($request->tags) > 0) {
                $tagIds = [];
                foreach ($request->tags as $tagName) {
                    $tag = Tag::firstOrCreate(['name' => (string) $tagName]);
                    $tagIds[] = $tag->id;
                }
                $proposal->tags()->sync($tagIds);
            }

            $proposal->load(['user', 'tags']);

            DB::commit();

            // Invalidate caches related to proposals
            CacheHelper::forgetProposalRelated($proposal->id);
            CacheHelper::forgetUserRelated($request->user()->id);

            // Broadcast proposal submitted event
            event(new ProposalSubmitted($proposal));

            return ApiResponse::success(
                'Proposal created successfully',
                ['proposal' => new ProposalResource($proposal)],
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();

            // Clean up uploaded file if transaction fails
            if (isset($filePath)) {
                Storage::disk(FileConstants::PROPOSAL_STORAGE_DISK)->delete($filePath);
            }

            Log::error('Error creating proposal', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Failed to create proposal', 500);
        }
    }

    /**
     * Display the specified proposal.
     */
    #[OA\Get(
        path: "/proposals/{id}",
        description: "Retrieves a single proposal by ID. Speakers can only view their own proposals, while reviewers and admins can view any proposal.",
        summary: "Get a specific proposal",
        security: [["sanctum" => []]],
        tags: ["Proposals"],
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
                description: "Proposal retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "message", type: "string", example: "Proposal retrieved successfully"),
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
            new OA\Response(response: 403, description: "Unauthorized"),
            new OA\Response(response: 404, description: "Proposal not found"),
            new OA\Response(response: 500, description: "Server error"),
        ]
    )]
    public function show(Request $request, Proposal $proposal): JsonResponse
    {
        try {
            $this->authorize('view', $proposal);

            $proposal->load(['user', 'tags']);

            return ApiResponse::success(
                'Proposal retrieved successfully',
                ['proposal' => new ProposalResource($proposal)]
            );
        } catch (AuthorizationException $e) {
            return ApiResponse::error('Unauthorized', 403);
        } catch (\Exception $e) {
            Log::error('Error retrieving proposal', [
                'proposal_id' => $proposal->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Failed to retrieve proposal', 500);
        }
    }

    /**
     * Get top-rated proposals for slider.
     */
    #[OA\Get(
        path: "/proposals/top-rated",
        description: "Retrieves approved proposals with an average rating of 4.0 or higher, ordered by rating and review count. Used for displaying featured proposals in a slider.",
        summary: "Get top-rated proposals",
        security: [["sanctum" => []]],
        tags: ["Proposals"],
        parameters: [
            new OA\Parameter(
                name: "limit",
                description: "Maximum number of proposals to return",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "integer", example: 10, default: 10)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Top-rated proposals retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "message", type: "string", example: "Top-rated proposals retrieved successfully"),
                        new OA\Property(
                            property: "data",
                            properties: [
                                new OA\Property(
                                    property: "proposals",
                                    type: "array",
                                    items: new OA\Items(ref: "#/components/schemas/Proposal")
                                ),
                            ],
                            type: "object"
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 500, description: "Server error"),
        ]
    )]
    public function topRated(Request $request): JsonResponse
    {
        try {
            $limit = min(
                max((int) $request->get('limit', PaginationConstants::DEFAULT_TOP_RATED_LIMIT), 1),
                PaginationConstants::MAX_TOP_RATED_LIMIT
            );

            // Use cache for top-rated proposals (15 minutes TTL)
            $proposals = CacheHelper::rememberTopRated(function () use ($limit) {
                return Proposal::select('proposals.*')
                    ->selectRaw('AVG(reviews.rating) as avg_rating')
                    ->selectRaw('COUNT(reviews.id) as reviews_count')
                    ->leftJoin('reviews', 'proposals.id', '=', 'reviews.proposal_id')
                    ->where('proposals.status', ProposalStatus::APPROVED->value)
                    ->groupBy('proposals.id')
                    ->havingRaw('AVG(reviews.rating) >= ?', [PaginationConstants::MIN_TOP_RATED_RATING])
                    ->havingRaw('COUNT(reviews.id) > 0')
                    ->with(['user', 'tags'])
                    ->orderByDesc('avg_rating')
                    ->orderByDesc('reviews_count')
                    ->limit($limit)
                    ->get()
                    ->map(function ($proposal) {
                        // Set the calculated values for the resource
                        $proposal->reviews_avg_rating = (float) $proposal->avg_rating;
                        $proposal->reviews_count = (int) $proposal->reviews_count;

                        return $proposal;
                    });
            }, $limit);

            return ApiResponse::success(
                'Top-rated proposals retrieved successfully',
                ['proposals' => ProposalResource::collection($proposals)]
            );
        } catch (\Exception $e) {
            Log::error('Error retrieving top-rated proposals', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Failed to retrieve top-rated proposals', 500);
        }
    }

    /**
     * Download the proposal file.
     */
    #[OA\Get(
        path: "/proposals/{id}/download",
        description: "Downloads the PDF file associated with a proposal. Requires authentication and appropriate permissions.",
        summary: "Download proposal PDF file",
        security: [["sanctum" => []]],
        tags: ["Proposals"],
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
                description: "File download",
                content: new OA\MediaType(
                    mediaType: "application/pdf"
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Unauthorized"),
            new OA\Response(response: 404, description: "File not found"),
            new OA\Response(response: 500, description: "Server error"),
        ]
    )]
    public function downloadFile(Request $request, Proposal $proposal): BinaryFileResponse|JsonResponse
    {
        try {
            $this->authorize('downloadFile', $proposal);

            if (! $proposal->file_path) {
                throw new ProposalFileNotFoundException;
            }

            $filePath = storage_path('app/'.FileConstants::PROPOSAL_STORAGE_DISK.'/'.$proposal->file_path);

            if (! file_exists($filePath)) {
                throw new ProposalFileNotFoundException;
            }

            return response()->download($filePath, basename($proposal->file_path), [
                'Content-Type' => FileConstants::ALLOWED_MIME_TYPES[0],
            ]);
        } catch (AuthorizationException $e) {
            return ApiResponse::error('Unauthorized', 403);
        } catch (ProposalFileNotFoundException $e) {
            return ApiResponse::error($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            Log::error('Error downloading proposal file', [
                'proposal_id' => $proposal->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Failed to download file', 500);
        }
    }

    /**
     * Update the specified proposal.
     */
    #[OA\Put(
        path: "/proposals/{id}",
        description: "Updates an existing proposal. Speakers can only update their own proposals. All fields are optional - only provided fields will be updated.",
        summary: "Update a proposal",
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: "title", type: "string", example: "Updated Title", description: "Proposal title (optional)"),
                        new OA\Property(property: "description", type: "string", example: "Updated description", description: "Proposal description (optional)"),
                        new OA\Property(property: "file", type: "string", format: "binary", description: "PDF file (optional, max 4MB)"),
                        new OA\Property(property: "tags", type: "array", items: new OA\Items(type: "string"), example: ["Technology", "Laravel"], description: "Array of tag names (optional, empty array removes all tags)"),
                    ]
                )
            )
        ),
        tags: ["Proposals"],
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
                description: "Proposal updated successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "message", type: "string", example: "Proposal updated successfully"),
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
            new OA\Response(response: 403, description: "Unauthorized"),
            new OA\Response(response: 404, description: "Proposal not found"),
            new OA\Response(response: 422, description: "Validation error"),
            new OA\Response(response: 500, description: "Server error"),
        ]
    )]
    public function update(UpdateProposalRequest $request, Proposal $proposal): JsonResponse
    {
        try {
            DB::beginTransaction();

            $data = [];

            if ($request->filled('title')) {
                $data['title'] = $request->string('title')->toString();
            }

            if ($request->filled('description')) {
                $data['description'] = $request->string('description')->toString();
            }

            // Handle file update
            if ($request->hasFile('file')) {
                // Delete old file
                if ($proposal->file_path) {
                    Storage::disk(FileConstants::PROPOSAL_STORAGE_DISK)->delete($proposal->file_path);
                }
                $file = $request->file('file');
                $data['file_path'] = $file->store(FileConstants::PROPOSAL_STORAGE_PATH, FileConstants::PROPOSAL_STORAGE_DISK);
            }

            if (count($data) > 0) {
                $proposal->update($data);
            }

            // Handle tags update - tags are optional
            if ($request->has('tags')) {
                if (is_array($request->tags) && count($request->tags) > 0) {
                    $tagIds = [];
                    foreach ($request->tags as $tagName) {
                        $tag = Tag::firstOrCreate(['name' => (string) $tagName]);
                        $tagIds[] = $tag->id;
                    }
                    $proposal->tags()->sync($tagIds);
                } else {
                    // If tags array is empty, remove all tags
                    $proposal->tags()->sync([]);
                }
            }

            $proposal->load(['user', 'tags']);

            DB::commit();

            // Invalidate caches related to proposals
            CacheHelper::forgetProposalRelated($proposal->id);
            CacheHelper::forgetUserRelated($proposal->user_id);
            // Invalidate tags cache if tags were updated
            if ($request->has('tags')) {
                CacheHelper::forgetTags();
            }

            return ApiResponse::success(
                'Proposal updated successfully',
                ['proposal' => new ProposalResource($proposal)]
            );
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error updating proposal', [
                'proposal_id' => $proposal->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Failed to update proposal', 500);
        }
    }

    /**
     * Remove the specified proposal.
     */
    #[OA\Delete(
        path: "/proposals/{id}",
        description: "Deletes a proposal and its associated file. Speakers can only delete their own proposals.",
        summary: "Delete a proposal",
        security: [["sanctum" => []]],
        tags: ["Proposals"],
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
                description: "Proposal deleted successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "message", type: "string", example: "Proposal deleted successfully"),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Unauthorized"),
            new OA\Response(response: 404, description: "Proposal not found"),
            new OA\Response(response: 500, description: "Server error"),
        ]
    )]
    public function destroy(Request $request, Proposal $proposal): JsonResponse
    {
        try {
            $this->authorize('delete', $proposal);

            DB::beginTransaction();

            // Delete file if exists
            if ($proposal->file_path) {
                Storage::disk(FileConstants::PROPOSAL_STORAGE_DISK)->delete($proposal->file_path);
            }

            $proposal->delete();

            DB::commit();

            // Invalidate caches related to proposals
            CacheHelper::forgetProposalRelated($proposal->id);
            CacheHelper::forgetUserRelated($proposal->user_id);

            return ApiResponse::success('Proposal deleted successfully');
        } catch (AuthorizationException $e) {
            return ApiResponse::error('Unauthorized', 403);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error deleting proposal', [
                'proposal_id' => $proposal->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Failed to delete proposal', 500);
        }
    }
}
