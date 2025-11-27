<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProposalStatusRequest;
use App\Http\Resources\ProposalResource;
use App\Models\Proposal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminProposalController extends Controller
{
    /**
     * Display a listing of all proposals for admin.
     */
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = Proposal::with(['user', 'tags', 'reviews']);

        // Search by title
        if ($request->has('search')) {
            $query->where('title', 'like', '%'.$request->search.'%');
        }

        // Filter by tags
        if ($request->has('tags')) {
            $tagIds = is_array($request->tags) ? $request->tags : explode(',', $request->tags);
            $query->whereHas('tags', function ($q) use ($tagIds) {
                $q->whereIn('tags.id', $tagIds);
            });
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $perPage = $request->get('per_page', 15);
        $proposals = $query->latest()->paginate($perPage);

        return response()->json([
            'proposals' => ProposalResource::collection($proposals->items()),
            'pagination' => [
                'current_page' => $proposals->currentPage(),
                'last_page' => $proposals->lastPage(),
                'per_page' => $proposals->perPage(),
                'total' => $proposals->total(),
            ],
        ]);
    }

    /**
     * Update the proposal status.
     */
    public function updateStatus(UpdateProposalStatusRequest $request, Proposal $proposal): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $proposal->update([
            'status' => $request->status,
        ]);

        $proposal->load(['user', 'tags']);

        return response()->json([
            'proposal' => new ProposalResource($proposal),
            'message' => 'Proposal status updated successfully',
        ]);
    }
}

