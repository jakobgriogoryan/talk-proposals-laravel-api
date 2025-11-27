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

/**
 * Controller for managing tags.
 */
class TagController extends Controller
{
    /**
     * Display a listing of tags.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Tag::query();

            if ($request->filled('search')) {
                $query->searchByName($request->string('search')->toString());
            }

            $tags = $query->orderBy('name')->get();

            return ApiResponse::success(
                'Tags retrieved successfully',
                ['tags' => TagResource::collection($tags)]
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
