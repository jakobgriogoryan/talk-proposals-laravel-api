<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ReviewRating;
use App\Events\ProposalReviewed;
use App\Exceptions\DuplicateReviewException;
use App\Helpers\ApiResponse;
use App\Http\Requests\StoreReviewRequest;
use App\Http\Requests\UpdateReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Proposal;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

/**
 * Controller for managing reviews.
 */
#[OA\Tag(name: "Reviews")]
class ReviewController extends Controller
{
    /**
     * Get available rating options.
     */
    #[OA\Get(
        path: "/reviews/rating-options",
        description: "Returns all available rating options (1-5 and 10) with their labels.",
        summary: "Get rating options",
        security: [["sanctum" => []]],
        tags: ["Reviews"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Rating options retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "message", type: "string", example: "Rating options retrieved successfully"),
                        new OA\Property(
                            property: "data",
                            properties: [
                                new OA\Property(
                                    property: "ratings",
                                    type: "array",
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: "value", type: "integer", example: 5),
                                            new OA\Property(property: "label", type: "string", example: "5 - Excellent"),
                                        ],
                                        type: "object"
                                    )
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
    public function ratingOptions(): JsonResponse
    {
        return ApiResponse::success(
            'Rating options retrieved successfully',
            ['ratings' => ReviewRating::options()]
        );
    }

    /**
     * Display a listing of reviews for a proposal.
     */
    #[OA\Get(
        path: "/proposals/{proposalId}/reviews",
        description: "Retrieves paginated reviews for a specific proposal, ordered by most recent first.",
        summary: "Get reviews for a proposal",
        security: [["sanctum" => []]],
        tags: ["Reviews"],
        parameters: [
            new OA\Parameter(
                name: "proposalId",
                in: "path",
                required: true,
                description: "Proposal ID",
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
                description: "Items per page (max 50)",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "integer", example: 10)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Reviews retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "message", type: "string", example: "Reviews retrieved successfully"),
                        new OA\Property(
                            property: "data",
                            properties: [
                                new OA\Property(
                                    property: "reviews",
                                    type: "array",
                                    items: new OA\Items(ref: "#/components/schemas/Review")
                                ),
                                new OA\Property(
                                    property: "pagination",
                                    properties: [
                                        new OA\Property(property: "current_page", type: "integer", example: 1),
                                        new OA\Property(property: "last_page", type: "integer", example: 3),
                                        new OA\Property(property: "per_page", type: "integer", example: 10),
                                        new OA\Property(property: "total", type: "integer", example: 25),
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
            new OA\Response(response: 404, description: "Proposal not found"),
            new OA\Response(response: 500, description: "Server error"),
        ]
    )]
    public function index(Request $request, Proposal $proposal): JsonResponse
    {
        try {
            // Paginate reviews to handle proposals with many reviews
            $perPage = min((int) $request->integer('per_page', 10), 50); // Max 50 per page
            $reviews = $proposal->reviews()->with('reviewer')->latest()->paginate($perPage);

            return ApiResponse::success(
                'Reviews retrieved successfully',
                [
                    'reviews' => ReviewResource::collection($reviews->items()),
                    'pagination' => [
                        'current_page' => $reviews->currentPage(),
                        'last_page' => $reviews->lastPage(),
                        'per_page' => $reviews->perPage(),
                        'total' => $reviews->total(),
                    ],
                ]
            );
        } catch (\Exception $e) {
            Log::error('Error retrieving reviews', [
                'proposal_id' => $proposal->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Failed to retrieve reviews', 500);
        }
    }

    /**
     * Store a newly created review.
     */
    #[OA\Post(
        path: "/proposals/{proposalId}/reviews",
        description: "Creates a new review for a proposal. Each reviewer can only review a proposal once. Rating must be 1, 2, 3, 4, 5, or 10.",
        summary: "Create a review",
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["rating"],
                properties: [
                    new OA\Property(property: "rating", type: "integer", enum: [1, 2, 3, 4, 5, 10], example: 5, description: "Rating value (1-5 or 10)"),
                    new OA\Property(property: "comment", type: "string", nullable: true, example: "Great proposal!", description: "Optional review comment"),
                ]
            )
        ),
        tags: ["Reviews"],
        parameters: [
            new OA\Parameter(
                name: "proposalId",
                description: "Proposal ID",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer", example: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 201,
                description: "Review created successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "message", type: "string", example: "Review created successfully"),
                        new OA\Property(
                            property: "data",
                            properties: [
                                new OA\Property(property: "review", ref: "#/components/schemas/Review"),
                            ],
                            type: "object"
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Unauthorized or duplicate review"),
            new OA\Response(response: 404, description: "Proposal not found"),
            new OA\Response(response: 422, description: "Validation error"),
            new OA\Response(response: 500, description: "Server error"),
        ]
    )]
    public function store(StoreReviewRequest $request, Proposal $proposal): JsonResponse
    {
        try {
            // Check if reviewer already reviewed this proposal
            $existingReview = Review::where('proposal_id', $proposal->id)
                ->where('reviewer_id', $request->user()->id)
                ->first();

            if ($existingReview) {
                throw new DuplicateReviewException;
            }

            DB::beginTransaction();

            $review = Review::create([
                'proposal_id' => $proposal->id,
                'reviewer_id' => $request->user()->id,
                'rating' => (int) $request->integer('rating'),
                'comment' => $request->filled('comment') ? $request->string('comment')->toString() : null,
            ]);

            $review->load('reviewer');
            $proposal->refresh()->load('user');

            DB::commit();

            // Broadcast proposal reviewed event
            event(new ProposalReviewed($proposal, $review));

            return ApiResponse::success(
                'Review created successfully',
                ['review' => new ReviewResource($review)],
                201
            );
        } catch (DuplicateReviewException $e) {
            return ApiResponse::error($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error creating review', [
                'proposal_id' => $proposal->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Failed to create review', 500);
        }
    }

    /**
     * Display the specified review.
     */
    #[OA\Get(
        path: "/proposals/{proposalId}/reviews/{reviewId}",
        description: "Retrieves a single review by ID for a specific proposal.",
        summary: "Get a specific review",
        security: [["sanctum" => []]],
        tags: ["Reviews"],
        parameters: [
            new OA\Parameter(
                name: "proposalId",
                description: "Proposal ID",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer", example: 1)
            ),
            new OA\Parameter(
                name: "reviewId",
                description: "Review ID",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer", example: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Review retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "message", type: "string", example: "Review retrieved successfully"),
                        new OA\Property(
                            property: "data",
                            properties: [
                                new OA\Property(property: "review", ref: "#/components/schemas/Review"),
                            ],
                            type: "object"
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Review or proposal not found"),
            new OA\Response(response: 500, description: "Server error"),
        ]
    )]
    public function show(Request $request, Proposal $proposal, Review $review): JsonResponse
    {
        try {
            if ($review->proposal_id !== $proposal->id) {
                return ApiResponse::error('Review not found for this proposal', 404);
            }

            $review->load('reviewer');

            return ApiResponse::success(
                'Review retrieved successfully',
                ['review' => new ReviewResource($review)]
            );
        } catch (\Exception $e) {
            Log::error('Error retrieving review', [
                'review_id' => $review->id,
                'proposal_id' => $proposal->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Failed to retrieve review', 500);
        }
    }

    /**
     * Update the specified review.
     */
    #[OA\Put(
        path: "/proposals/{proposalId}/reviews/{reviewId}",
        description: "Updates an existing review. Only admins can update reviews. Rating must be 1, 2, 3, 4, 5, or 10.",
        summary: "Update a review",
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["rating"],
                properties: [
                    new OA\Property(property: "rating", type: "integer", enum: [1, 2, 3, 4, 5, 10], example: 5, description: "Rating value (1-5 or 10)"),
                    new OA\Property(property: "comment", type: "string", nullable: true, example: "Updated comment", description: "Optional review comment"),
                ]
            )
        ),
        tags: ["Reviews"],
        parameters: [
            new OA\Parameter(
                name: "proposalId",
                description: "Proposal ID",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer", example: 1)
            ),
            new OA\Parameter(
                name: "reviewId",
                description: "Review ID",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer", example: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Review updated successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "message", type: "string", example: "Review updated successfully"),
                        new OA\Property(
                            property: "data",
                            properties: [
                                new OA\Property(property: "review", ref: "#/components/schemas/Review"),
                            ],
                            type: "object"
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Unauthorized - Admin only"),
            new OA\Response(response: 404, description: "Review or proposal not found"),
            new OA\Response(response: 422, description: "Validation error"),
            new OA\Response(response: 500, description: "Server error"),
        ]
    )]
    public function update(UpdateReviewRequest $request, Proposal $proposal, Review $review): JsonResponse
    {
        try {
            // Check if review belongs to proposal
            if ($review->proposal_id !== $proposal->id) {
                return ApiResponse::error('Review not found for this proposal', 404);
            }

            DB::beginTransaction();

            $review->update([
                'rating' => (int) $request->integer('rating'),
                'comment' => $request->filled('comment') ? $request->string('comment')->toString() : null,
            ]);

            $review->load('reviewer');

            DB::commit();

            return ApiResponse::success(
                'Review updated successfully',
                ['review' => new ReviewResource($review)]
            );
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error updating review', [
                'review_id' => $review->id,
                'proposal_id' => $proposal->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Failed to update review', 500);
        }
    }
}
