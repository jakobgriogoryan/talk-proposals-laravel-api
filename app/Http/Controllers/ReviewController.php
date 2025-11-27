<?php

declare(strict_types=1);

namespace App\Http\Controllers;

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

/**
 * Controller for managing reviews.
 */
class ReviewController extends Controller
{
    /**
     * Display a listing of reviews for a proposal.
     */
    public function index(Request $request, Proposal $proposal): JsonResponse
    {
        try {
            $reviews = $proposal->reviews()->with('reviewer')->latest()->get();

            return ApiResponse::success(
                'Reviews retrieved successfully',
                ['reviews' => ReviewResource::collection($reviews)]
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

            DB::commit();

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
