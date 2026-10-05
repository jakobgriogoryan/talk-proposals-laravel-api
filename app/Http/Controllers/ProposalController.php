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
use App\Http\Requests\IndexProposalRequest;
use App\Http\Requests\StoreProposalRequest;
use App\Http\Requests\TopRatedProposalRequest;
use App\Http\Requests\UpdateProposalRequest;
use App\Http\Resources\ProposalResource;
use App\Jobs\IndexProposalJob;
use App\Jobs\ProcessProposalFileJob;
use App\Models\Proposal;
use App\Models\Tag;
use App\Services\FileUploadService;
use App\Services\ProposalSearchService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Controller for managing proposals.
 */
#[OA\Tag(name: 'Proposals')]
class ProposalController extends Controller
{
    public function __construct(private readonly ProposalSearchService $search) {}

    /**
     * Display a listing of proposals for reviewers.
     */
    #[OA\Get(
        path: '/review/proposals',
        description: 'Lists proposals for reviewers and admins. Database title search is the default and remote-outage fallback. Optional Algolia or Elasticsearch enables configured full-text search. Ownership, tags, status, and pagination constraints apply to every engine.',
        summary: 'List all proposals for review (Reviewer or Admin)',
        security: [['sanctum' => []]],
        tags: ['Reviews'],
        parameters: [
            new OA\Parameter(
                name: 'search',
                description: 'Database title search by default and on remote outages. Configured Algolia or Elasticsearch provides full-text search across title, description, tags, and author name.',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', example: 'Laravel framework')
            ),
            new OA\Parameter(
                name: 'tags',
                description: 'Filter by tag IDs (comma-separated or array)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', example: '1,2,3')
            ),
            new OA\Parameter(
                name: 'status',
                description: 'Filter by status',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['pending', 'approved', 'rejected'], example: 'pending')
            ),
            new OA\Parameter(
                name: 'page',
                description: 'Page number',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
            new OA\Parameter(
                name: 'per_page',
                description: 'Items per page',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', example: 15)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Proposals retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'message', type: 'string', example: 'Proposals retrieved successfully'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(
                                    property: 'proposals',
                                    type: 'array',
                                    items: new OA\Items(ref: '#/components/schemas/Proposal')
                                ),
                                new OA\Property(
                                    property: 'pagination',
                                    properties: [
                                        new OA\Property(property: 'current_page', type: 'integer', example: 1),
                                        new OA\Property(property: 'last_page', type: 'integer', example: 5),
                                        new OA\Property(property: 'per_page', type: 'integer', example: 15),
                                        new OA\Property(property: 'total', type: 'integer', example: 75),
                                    ],
                                    type: 'object'
                                ),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden - Reviewer or Admin required'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    public function indexForReview(IndexProposalRequest $request): JsonResponse
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
        path: '/proposals',
        description: 'Lists proposals with ownership, tags, status, and pagination constraints. Speakers see their own proposals; reviewers and admins see all. Database title search is the default and remote-outage fallback; configured Algolia or Elasticsearch enables full-text search.',
        summary: 'List proposals',
        security: [['sanctum' => []]],
        tags: ['Proposals'],
        parameters: [
            new OA\Parameter(
                name: 'search',
                description: 'Search proposals by title',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', example: 'Laravel')
            ),
            new OA\Parameter(
                name: 'tags',
                description: 'Filter by tag IDs (comma-separated or array)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', example: '1,2,3')
            ),
            new OA\Parameter(
                name: 'status',
                description: 'Filter by status',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['pending', 'approved', 'rejected'], example: 'pending')
            ),
            new OA\Parameter(
                name: 'page',
                description: 'Page number',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
            new OA\Parameter(
                name: 'per_page',
                description: 'Items per page',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', example: 15)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Proposals retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'message', type: 'string', example: 'Proposals retrieved successfully'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(
                                    property: 'proposals',
                                    type: 'array',
                                    items: new OA\Items(ref: '#/components/schemas/Proposal')
                                ),
                                new OA\Property(
                                    property: 'pagination',
                                    properties: [
                                        new OA\Property(property: 'current_page', type: 'integer', example: 1),
                                        new OA\Property(property: 'last_page', type: 'integer', example: 5),
                                        new OA\Property(property: 'per_page', type: 'integer', example: 15),
                                        new OA\Property(property: 'total', type: 'integer', example: 75),
                                    ],
                                    type: 'object'
                                ),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    public function index(IndexProposalRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $perPage = isset($validated['per_page']) ? (int) $validated['per_page'] : PaginationConstants::DEFAULT_PER_PAGE;
            $proposals = $this->search->paginate($request->user(), $validated, (int) $perPage);

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
            $this->logError('Error retrieving proposals', $e, $request);

            return ApiResponse::error('Failed to retrieve proposals', 500);
        }
    }

    /**
     * Store a newly created proposal.
     */
    #[OA\Post(
        path: '/proposals',
        description: "Creates a new talk proposal. File upload is optional (PDF, max 4MB). Tags can be provided as an array of strings (will be created if they don't exist). Status defaults to 'pending'.",
        summary: 'Create a new proposal',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['title', 'description'],
                    properties: [
                        new OA\Property(property: 'title', description: 'Proposal title (required)', type: 'string', example: 'Introduction to Laravel'),
                        new OA\Property(property: 'description', description: 'Proposal description (required)', type: 'string', example: 'A comprehensive guide to Laravel framework'),
                        new OA\Property(property: 'file', description: 'PDF file (optional, max 4MB)', type: 'string', format: 'binary'),
                        new OA\Property(property: 'tags', description: 'Array of tag names (optional)', type: 'array', items: new OA\Items(type: 'string'), example: ['Technology', 'Laravel']),
                    ]
                )
            )
        ),
        tags: ['Proposals'],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Proposal created successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'message', type: 'string', example: 'Proposal created successfully'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'proposal', ref: '#/components/schemas/Proposal'),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Unauthorized'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    public function store(StoreProposalRequest $request): JsonResponse
    {
        $filePath = null;
        $proposal = null;
        $committed = false;
        $transactionLevel = DB::transactionLevel();

        try {
            DB::beginTransaction();

            $validated = $request->validated();

            // File is already validated by StoreProposalRequest
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                // Store file immediately (request-level validation already passed)
                // Domain-level validation will happen in background job
                $filePath = $file->store(FileConstants::PROPOSAL_STORAGE_PATH, FileConstants::PROPOSAL_STORAGE_DISK);

                if (! $filePath) {
                    throw new \RuntimeException('Failed to store file');
                }
            }

            $proposal = Proposal::create([
                'user_id' => $request->user()->id,
                'title' => $validated['title'],
                'description' => $validated['description'],
                'file_path' => $filePath,
                'status' => ProposalStatus::PENDING->value,
            ]);

            // Handle tags (create if not exists, then attach) - tags are optional
            if (isset($validated['tags']) && is_array($validated['tags']) && count($validated['tags']) > 0) {
                $tagIds = [];
                foreach ($validated['tags'] as $tagName) {
                    $tag = Tag::firstOrCreate(['name' => (string) $tagName]);
                    $tagIds[] = $tag->id;
                }
                $proposal->tags()->sync($tagIds);
            }

            $proposal->load(['user', 'tags']);

            DB::commit();
            $committed = true;

            // Invalidate caches related to proposals
            CacheHelper::forgetProposalRelated($proposal->id);
            CacheHelper::forgetUserRelated($request->user()->id);
            if (! empty($validated['tags'])) {
                CacheHelper::forgetTags();
            }

            // Broadcast proposal submitted event (for real-time updates and background jobs)
            // Scout handles indexing; event listeners process files and notifications.
            event(new ProposalSubmitted($proposal, $filePath, $request->user()->id));

            return ApiResponse::success(
                'Proposal created successfully',
                ['proposal' => new ProposalResource($proposal)],
                201
            );
        } catch (\Exception $e) {
            // Commit callbacks may throw after the database write is durable.
            $committed = $committed || ($proposal?->exists && DB::transactionLevel() === $transactionLevel);
            if ($committed) {
                $this->logError('Post-commit proposal processing failed', $e, $request);

                return ApiResponse::success(
                    'Proposal created successfully',
                    ['proposal' => new ProposalResource($proposal)],
                    201
                );
            }

            if (DB::transactionLevel() > $transactionLevel) {
                DB::rollBack($transactionLevel);
            }

            if ($filePath) {
                try {
                    app(FileUploadService::class)->deleteFile($filePath);
                } catch (\Exception $cleanupException) {
                    Log::warning('Failed to cleanup file after proposal creation error', [
                        'file_path' => $filePath,
                        'error' => $cleanupException->getMessage(),
                    ]);
                }
            }

            if ($e instanceof \InvalidArgumentException) {
                return ApiResponse::error($e->getMessage(), 422);
            }

            $this->logError('Error creating proposal', $e, $request);

            return ApiResponse::error('Failed to create proposal', 500);
        }
    }

    /**
     * Display the specified proposal.
     */
    #[OA\Get(
        path: '/proposals/{id}',
        description: 'Retrieves a single proposal by ID. Speakers can only view their own proposals, while reviewers and admins can view any proposal.',
        summary: 'Get a specific proposal',
        security: [['sanctum' => []]],
        tags: ['Proposals'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Proposal ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Proposal retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'message', type: 'string', example: 'Proposal retrieved successfully'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'proposal', ref: '#/components/schemas/Proposal'),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Proposal not found'),
            new OA\Response(response: 500, description: 'Server error'),
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
            $this->logError('Error retrieving proposal', $e, $request, [
                'proposal_id' => $proposal->id,
            ]);

            return ApiResponse::error('Failed to retrieve proposal', 500);
        }
    }

    /**
     * Get top-rated proposals for slider.
     */
    #[OA\Get(
        path: '/proposals/top-rated',
        description: 'Retrieves approved proposals with an average rating of 4.0 or higher, ordered by rating and review count. Used for displaying featured proposals in a slider.',
        summary: 'Get top-rated proposals',
        security: [['sanctum' => []]],
        tags: ['Proposals'],
        parameters: [
            new OA\Parameter(
                name: 'limit',
                description: 'Maximum number of proposals to return',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', example: 10, default: 10)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Top-rated proposals retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'message', type: 'string', example: 'Top-rated proposals retrieved successfully'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(
                                    property: 'proposals',
                                    type: 'array',
                                    items: new OA\Items(ref: '#/components/schemas/Proposal')
                                ),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    public function topRated(TopRatedProposalRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $limit = (int) ($validated['limit'] ?? PaginationConstants::DEFAULT_TOP_RATED_LIMIT);

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
            $this->logError('Error retrieving top-rated proposals', $e, $request);

            return ApiResponse::error('Failed to retrieve top-rated proposals', 500);
        }
    }

    /**
     * Download the proposal file.
     */
    #[OA\Get(
        path: '/proposals/{id}/download',
        description: 'Downloads the PDF file associated with a proposal. Requires authentication and appropriate permissions.',
        summary: 'Download proposal PDF file',
        security: [['sanctum' => []]],
        tags: ['Proposals'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Proposal ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'File download',
                content: new OA\MediaType(
                    mediaType: 'application/pdf'
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'File not found'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    public function downloadFile(Request $request, Proposal $proposal): BinaryFileResponse|JsonResponse
    {
        try {
            $this->authorize('downloadFile', $proposal);

            if (! $proposal->file_path) {
                throw new ProposalFileNotFoundException;
            }

            $filePath = Storage::disk(FileConstants::PROPOSAL_STORAGE_DISK)->path($proposal->file_path);

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
            $this->logError('Error downloading proposal file', $e, $request, [
                'proposal_id' => $proposal->id,
            ]);

            return ApiResponse::error('Failed to download file', 500);
        }
    }

    /**
     * Update the specified proposal.
     */
    #[OA\Put(
        path: '/proposals/{id}',
        description: 'Updates an existing proposal. Speakers can only update their own proposals. All fields are optional - only provided fields will be updated.',
        summary: 'Update a proposal',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(ref: '#/components/requestBodies/ProposalUpdate'),
        tags: ['Proposals'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Proposal ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Proposal updated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'message', type: 'string', example: 'Proposal updated successfully'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'proposal', ref: '#/components/schemas/Proposal'),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Proposal not found'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    #[OA\Patch(
        path: '/proposals/{id}',
        summary: 'Partially update a proposal',
        description: 'Same validation and ownership rules as PUT. Browser multipart uploads use POST with _method=PATCH; tags=[] clears tags and omitted fields remain unchanged.',
        security: [['sanctum' => []]],
        tags: ['Proposals'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(ref: '#/components/requestBodies/ProposalUpdate'),
        responses: [
            new OA\Response(response: 200, description: 'Proposal updated successfully', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Not authorized to update this proposal'),
            new OA\Response(response: 404, description: 'Proposal not found'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    public function update(UpdateProposalRequest $request, Proposal $proposal): JsonResponse
    {
        $newFilePath = null;
        $committed = false;
        $transactionLevel = DB::transactionLevel();
        try {
            DB::beginTransaction();
            $proposal = Proposal::query()->lockForUpdate()->findOrFail($proposal->id);

            $validated = $request->validated();
            $data = [];

            if (isset($validated['title'])) {
                $data['title'] = $validated['title'];
            }

            if (isset($validated['description'])) {
                $data['description'] = $validated['description'];
            }

            // Handle file update
            // File is already validated by UpdateProposalRequest
            $fileChanged = false;
            if ($request->hasFile('file')) {
                $oldFilePath = $proposal->file_path;
                $file = $request->file('file');
                $newFilePath = app(FileUploadService::class)->storeAndValidateDomain($file, $proposal->user_id, $oldFilePath);

                if ($oldFilePath) {
                    DB::afterCommit(function () use ($oldFilePath): void {
                        try {
                            app(FileUploadService::class)->deleteFile($oldFilePath);
                        } catch (\Exception $exception) {
                            Log::warning('Unable to clean up replaced proposal file', ['file_path' => $oldFilePath]);
                        }
                    });
                }

                $data['file_path'] = $newFilePath;
                $fileChanged = true;
            }

            if (count($data) > 0) {
                $proposal->update($data);
            }

            // Handle tags update - tags are optional
            if (isset($validated['tags'])) {
                if (is_array($validated['tags']) && count($validated['tags']) > 0) {
                    $tagIds = [];
                    foreach ($validated['tags'] as $tagName) {
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
            $committed = true;

            // Invalidate caches related to proposals
            CacheHelper::forgetProposalRelated($proposal->id);
            CacheHelper::forgetUserRelated($proposal->user_id);
            // Invalidate tags cache if tags were updated
            if (isset($validated['tags'])) {
                CacheHelper::forgetTags();
            }

            // Dispatch background jobs
            if ($fileChanged) {
                // Process file in background (domain-level validation)
                ProcessProposalFileJob::dispatch($proposal, $newFilePath, $proposal->user_id);
            }

            // Scout handles model saves; tag-only edits do not fire a saved event.
            if (count($data) === 0 && isset($validated['tags'])) {
                IndexProposalJob::dispatch($proposal);
            }

            return ApiResponse::success(
                'Proposal updated successfully',
                ['proposal' => new ProposalResource($proposal)]
            );
        } catch (\Exception $e) {
            // After-commit callbacks can fail after the database write is durable.
            $committed = $committed || DB::transactionLevel() === $transactionLevel;
            if (! $committed) {
                DB::rollBack();
            }

            if (! $committed && $newFilePath) {
                try {
                    app(FileUploadService::class)->deleteFile($newFilePath);
                } catch (\Exception $cleanupException) {
                    Log::warning('Unable to clean up failed proposal replacement', ['file_path' => $newFilePath]);
                }
            }
            if ($e instanceof \InvalidArgumentException) {
                return ApiResponse::error($e->getMessage(), 422);
            }

            $this->logError('Error updating proposal', $e, $request, [
                'proposal_id' => $proposal->id,
            ]);

            return ApiResponse::error('Failed to update proposal', 500);
        }
    }

    /**
     * Remove the specified proposal.
     */
    #[OA\Delete(
        path: '/proposals/{id}',
        description: 'Deletes a proposal and its associated file. Speakers can only delete their own proposals.',
        summary: 'Delete a proposal',
        security: [['sanctum' => []]],
        tags: ['Proposals'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Proposal ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Proposal deleted successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'message', type: 'string', example: 'Proposal deleted successfully'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Proposal not found'),
            new OA\Response(response: 500, description: 'Server error'),
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

            $this->logError('Error deleting proposal', $e, $request, [
                'proposal_id' => $proposal->id,
            ]);

            return ApiResponse::error('Failed to delete proposal', 500);
        }
    }
}
