<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Constants\PaginationConstants;
use App\Enums\ProposalStatus;
use App\Events\ProposalStatusChanged;
use App\Exceptions\UnauthorizedException;
use App\Helpers\ApiResponse;
use App\Helpers\CacheHelper;
use App\Http\Requests\IndexAdminProposalRequest;
use App\Http\Requests\UpdateProposalStatusRequest;
use App\Http\Resources\ProposalResource;
use App\Models\Proposal;
use App\Services\ProposalSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

/**
 * Controller for admin proposal management.
 */
#[OA\Tag(name: 'Admin')]
class AdminProposalController extends Controller
{
    public function __construct(private readonly ProposalSearchService $search) {}

    /**
     * Display a listing of all proposals for admin.
     */
    #[OA\Get(
        path: '/admin/proposals',
        description: 'Retrieves all proposals with filtering options. Only accessible by admin users. Includes additional filters like user_id.',
        summary: 'List all proposals (Admin only)',
        security: [['sanctum' => []]],
        tags: ['Admin'],
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
                description: 'Filter by tag IDs (comma-separated)',
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
                name: 'user_id',
                description: 'Filter by user ID',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', example: 1)
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
            new OA\Response(response: 403, description: 'Forbidden - Admin only'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    public function index(IndexAdminProposalRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $perPage = $validated['per_page'] ?? PaginationConstants::DEFAULT_PER_PAGE;
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
        } catch (UnauthorizedException $e) {
            return ApiResponse::error($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            $this->logError('Error retrieving admin proposals', $e, $request);

            return ApiResponse::error('Failed to retrieve proposals', 500);
        }
    }

    /**
     * Update the proposal status.
     */
    #[OA\Patch(
        path: '/admin/proposals/{id}/status',
        description: 'Updates the status of a proposal. Only accessible by admin users. Triggers real-time broadcast event.',
        summary: 'Update proposal status (Admin only)',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['status'],
                properties: [
                    new OA\Property(property: 'status', type: 'string', enum: ['pending', 'approved', 'rejected'], example: 'approved', description: 'New proposal status'),
                ]
            )
        ),
        tags: ['Admin'],
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
                description: 'Proposal status updated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'message', type: 'string', example: 'Proposal status updated successfully'),
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
            new OA\Response(response: 403, description: 'Forbidden - Admin only'),
            new OA\Response(response: 404, description: 'Proposal not found'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    public function updateStatus(UpdateProposalStatusRequest $request, Proposal $proposal): JsonResponse
    {
        $transactionLevel = DB::transactionLevel();
        $commitAttempted = false;
        try {
            DB::beginTransaction();

            // Get old status as string (status is cast to ProposalStatus enum)
            $oldStatus = $proposal->status instanceof ProposalStatus
                ? $proposal->status->value
                : (string) $proposal->status;

            $validated = $request->validated();
            $status = ProposalStatus::from($validated['status']);

            $proposal->update([
                'status' => $status->value,
            ]);

            $proposal->load(['user', 'tags']);

            $commitAttempted = true;
            DB::commit();
        } catch (\Throwable $e) {
            if (! $commitAttempted || DB::transactionLevel() > $transactionLevel) {
                if (DB::transactionLevel() > $transactionLevel) {
                    DB::rollBack($transactionLevel);
                }
                $this->logError('Error updating proposal status', $e, $request, ['proposal_id' => $proposal->id]);

                return ApiResponse::error('Failed to update proposal status', 500);
            }
            $this->logError('Post-commit status callback failed', $e, $request, ['proposal_id' => $proposal->id]);
        }

        $this->runPostCommitAction(
            fn () => CacheHelper::forgetProposalRelated($proposal->id),
            'Post-commit status cache invalidation failed', $request, ['proposal_id' => $proposal->id]
        );
        $this->runPostCommitAction(
            fn () => CacheHelper::forgetUserRelated($proposal->user_id),
            'Post-commit user cache invalidation failed', $request, ['proposal_id' => $proposal->id]
        );
        if ($oldStatus !== $status->value) {
            $this->runPostCommitAction(
                fn () => event(new ProposalStatusChanged($proposal, $oldStatus, $status->value)),
                'Post-commit status event dispatch failed', $request, ['proposal_id' => $proposal->id]
            );
        }

        return ApiResponse::success('Proposal status updated successfully', ['proposal' => new ProposalResource($proposal)]);
    }
}
