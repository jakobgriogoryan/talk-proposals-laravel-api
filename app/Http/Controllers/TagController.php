<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\StoreTagRequest;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

/**
 * Controller for managing tags.
 */
#[OA\Tag(name: "Tags")]
class TagController extends Controller
{
    /**
     * Display a listing of tags.
     */
    #[OA\Get(
        path: "/tags",
        description: "Retrieves all available tags. Supports optional search by name.",
        summary: "List all tags",
        security: [["sanctum" => []]],
        tags: ["Tags"],
        parameters: [
            new OA\Parameter(
                name: "search",
                description: "Search tags by name",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "string", example: "Technology")
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
                description: "Items per page (max 100)",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "integer", example: 50)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Tags retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "message", type: "string", example: "Tags retrieved successfully"),
                        new OA\Property(
                            property: "data",
                            properties: [
                                new OA\Property(
                                    property: "tags",
                                    type: "array",
                                    items: new OA\Items(ref: "#/components/schemas/Tag")
                                ),
                                new OA\Property(
                                    property: "pagination",
                                    properties: [
                                        new OA\Property(property: "current_page", type: "integer", example: 1),
                                        new OA\Property(property: "last_page", type: "integer", example: 2),
                                        new OA\Property(property: "per_page", type: "integer", example: 50),
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
            $query = Tag::query();

            if ($request->filled('search')) {
                $query->searchByName($request->string('search')->toString());
            }

            // Paginate tags to handle large datasets efficiently
            $perPage = min((int) $request->integer('per_page', 50), 100); // Max 100 per page
            $tags = $query->orderBy('name')->paginate($perPage);

            return ApiResponse::success(
                'Tags retrieved successfully',
                [
                    'tags' => TagResource::collection($tags->items()),
                    'pagination' => [
                        'current_page' => $tags->currentPage(),
                        'last_page' => $tags->lastPage(),
                        'per_page' => $tags->perPage(),
                        'total' => $tags->total(),
                    ],
                ]
            );
        } catch (\Exception $e) {
            Log::error('Error retrieving tags', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Failed to retrieve tags', 500);
        }
    }

    /**
     * Store a newly created tag or return existing one.
     */
    #[OA\Post(
        path: "/tags",
        description: "Creates a new tag or returns the existing tag if a tag with the same name already exists.",
        summary: "Create a new tag",
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name"],
                properties: [
                    new OA\Property(property: "name", description: "Tag name", type: "string", example: "Technology"),
                ]
            )
        ),
        tags: ["Tags"],
        responses: [
            new OA\Response(
                response: 201,
                description: "Tag created successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "message", type: "string", example: "Tag created successfully"),
                        new OA\Property(
                            property: "data",
                            properties: [
                                new OA\Property(property: "tag", ref: "#/components/schemas/Tag"),
                            ],
                            type: "object"
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 422, description: "Validation error"),
            new OA\Response(response: 500, description: "Server error"),
        ]
    )]
    public function store(StoreTagRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $tag = Tag::firstOrCreate([
                'name' => $request->string('name')->toString(),
            ]);

            DB::commit();

            return ApiResponse::success(
                'Tag created successfully',
                ['tag' => new TagResource($tag)],
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error creating tag', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Failed to create tag', 500);
        }
    }
}
