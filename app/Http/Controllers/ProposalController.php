<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProposalRequest;
use App\Http\Requests\UpdateProposalRequest;
use App\Http\Resources\ProposalResource;
use App\Models\Proposal;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProposalController extends Controller
{
    /**
     * Display a listing of proposals.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Proposal::with(['user', 'tags']);

        // Filter by authenticated user if speaker
        if ($request->user()->isSpeaker() && ! $request->user()->isAdmin()) {
            $query->where('user_id', $request->user()->id);
        }

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
     * Store a newly created proposal.
     */
    public function store(StoreProposalRequest $request): JsonResponse
    {
        $filePath = null;
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $filePath = $file->store('proposals', 'public');
        }

        $proposal = Proposal::create([
            'user_id' => $request->user()->id,
            'title' => $request->title,
            'description' => $request->description,
            'file_path' => $filePath,
            'status' => 'pending',
        ]);

        // Handle tags (create if not exists, then attach)
        $tagIds = [];
        foreach ($request->tags as $tagName) {
            $tag = Tag::firstOrCreate(['name' => $tagName]);
            $tagIds[] = $tag->id;
        }
        $proposal->tags()->sync($tagIds);

        $proposal->load(['user', 'tags']);

        return response()->json([
            'proposal' => new ProposalResource($proposal),
            'message' => 'Proposal created successfully',
        ], 201);
    }

    /**
     * Display the specified proposal.
     */
    public function show(Request $request, Proposal $proposal): JsonResponse
    {
        // Check authorization
        if ($request->user()->isSpeaker() && ! $request->user()->isAdmin()) {
            if ($proposal->user_id !== $request->user()->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $proposal->load(['user', 'tags', 'reviews.reviewer']);

        return response()->json([
            'proposal' => new ProposalResource($proposal),
        ]);
    }

    /**
     * Update the specified proposal.
     */
    public function update(UpdateProposalRequest $request, Proposal $proposal): JsonResponse
    {
        // Check authorization
        if ($proposal->user_id !== $request->user()->id && ! $request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $data = [
            'title' => $request->title ?? $proposal->title,
            'description' => $request->description ?? $proposal->description,
        ];

        // Handle file update
        if ($request->hasFile('file')) {
            // Delete old file
            if ($proposal->file_path) {
                Storage::disk('public')->delete($proposal->file_path);
            }
            $file = $request->file('file');
            $data['file_path'] = $file->store('proposals', 'public');
        }

        $proposal->update($data);

        // Handle tags update
        if ($request->has('tags')) {
            $tagIds = [];
            foreach ($request->tags as $tagName) {
                $tag = Tag::firstOrCreate(['name' => $tagName]);
                $tagIds[] = $tag->id;
            }
            $proposal->tags()->sync($tagIds);
        }

        $proposal->load(['user', 'tags']);

        return response()->json([
            'proposal' => new ProposalResource($proposal),
            'message' => 'Proposal updated successfully',
        ]);
    }

    /**
     * Remove the specified proposal.
     */
    public function destroy(Request $request, Proposal $proposal): JsonResponse
    {
        // Check authorization
        if ($proposal->user_id !== $request->user()->id && ! $request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Delete file if exists
        if ($proposal->file_path) {
            Storage::disk('public')->delete($proposal->file_path);
        }

        $proposal->delete();

        return response()->json([
            'message' => 'Proposal deleted successfully',
        ]);
    }
}

