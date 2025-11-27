<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Constants\FileConstants;
use App\Constants\PaginationConstants;
use App\Enums\ProposalStatus;
use App\Exceptions\ProposalFileNotFoundException;
use App\Helpers\ApiResponse;
use App\Http\Requests\StoreProposalRequest;
use App\Http\Requests\UpdateProposalRequest;
use App\Http\Resources\ProposalResource;
use App\Models\Proposal;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Controller for managing proposals.
 */
class ProposalController extends Controller
{
    /**
     * Display a listing of proposals.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', Proposal::class);

            $query = Proposal::with(['user', 'tags']);

            // Filter by authenticated user if speaker
            if ($request->user()->isSpeaker() && ! $request->user()->isAdmin()) {
                $query->byUser($request->user()->id);
            }

            // Search by title
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

            $perPage = min(
                max((int) $request->get('per_page', PaginationConstants::DEFAULT_PER_PAGE), PaginationConstants::MIN_PER_PAGE),
                PaginationConstants::MAX_PER_PAGE
            );

            $proposals = $query->latest()->paginate($perPage);

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
     * Store a newly created proposal.
     */
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
    public function show(Request $request, Proposal $proposal): JsonResponse
    {
        try {
            $this->authorize('view', $proposal);

            $proposal->load(['user', 'tags']);

            return ApiResponse::success(
                'Proposal retrieved successfully',
                ['proposal' => new ProposalResource($proposal)]
            );
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
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
    public function topRated(Request $request): JsonResponse
    {
        try {
            $limit = min(
                max((int) $request->get('limit', PaginationConstants::DEFAULT_TOP_RATED_LIMIT), 1),
                PaginationConstants::MAX_TOP_RATED_LIMIT
            );

            $proposals = Proposal::select('proposals.*')
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
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
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

            return ApiResponse::success('Proposal deleted successfully');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
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
