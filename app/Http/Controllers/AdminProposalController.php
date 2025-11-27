<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Constants\PaginationConstants;
use App\Enums\ProposalStatus;
use App\Exceptions\UnauthorizedException;
use App\Helpers\ApiResponse;
use App\Http\Requests\UpdateProposalStatusRequest;
use App\Http\Resources\ProposalResource;
use App\Models\Proposal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Controller for admin proposal management.
 */
class AdminProposalController extends Controller
{
    /**
     * Display a listing of all proposals for admin.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            if (! $request->user()->isAdmin()) {
                throw new UnauthorizedException;
            }

            $query = Proposal::with(['user', 'tags', 'reviews']);

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

            // Filter by user
            if ($request->filled('user_id')) {
                $query->byUser((int) $request->integer('user_id'));
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
     * Update the proposal status.
     */
    public function updateStatus(UpdateProposalStatusRequest $request, Proposal $proposal): JsonResponse
    {
        try {
            DB::beginTransaction();

            $status = ProposalStatus::from($request->string('status')->toString());

            $proposal->update([
                'status' => $status->value,
            ]);

            $proposal->load(['user', 'tags']);

            DB::commit();

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
