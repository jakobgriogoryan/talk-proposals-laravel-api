<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Proposal;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /**
     * Display a listing of reviews for a proposal.
     */
    public function index(Request $request, Proposal $proposal): JsonResponse
    {
        $reviews = $proposal->reviews()->with('reviewer')->latest()->get();

        return response()->json([
            'reviews' => ReviewResource::collection($reviews),
        ]);
    }

    /**
     * Store a newly created review.
     */
    public function store(StoreReviewRequest $request, Proposal $proposal): JsonResponse
    {
        // Check if user is reviewer
        if (! $request->user()->isReviewer()) {
            return response()->json(['message' => 'Only reviewers can create reviews'], 403);
        }

        // Check if reviewer already reviewed this proposal
        $existingReview = Review::where('proposal_id', $proposal->id)
            ->where('reviewer_id', $request->user()->id)
            ->first();

        if ($existingReview) {
            return response()->json(['message' => 'You have already reviewed this proposal'], 422);
        }

        $review = Review::create([
            'proposal_id' => $proposal->id,
            'reviewer_id' => $request->user()->id,
            'rating' => $request->rating,
            'comment' => $request->comment,
        ]);

        $review->load('reviewer');

        return response()->json([
            'review' => new ReviewResource($review),
            'message' => 'Review created successfully',
        ], 201);
    }

    /**
     * Display the specified review.
     */
    public function show(Request $request, Proposal $proposal, Review $review): JsonResponse
    {
        if ($review->proposal_id !== $proposal->id) {
            return response()->json(['message' => 'Review not found for this proposal'], 404);
        }

        $review->load('reviewer');

        return response()->json([
            'review' => new ReviewResource($review),
        ]);
    }
}

