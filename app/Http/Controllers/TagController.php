<?php

namespace App\Http\Controllers;

use App\Http\Resources\TagResource;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TagController extends Controller
{
    /**
     * Display a listing of tags.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Tag::query();

        if ($request->has('search')) {
            $query->where('name', 'like', '%'.$request->search.'%');
        }

        $tags = $query->orderBy('name')->get();

        return response()->json([
            'tags' => TagResource::collection($tags),
        ]);
    }

    /**
     * Store a newly created tag or return existing one.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $tag = Tag::firstOrCreate([
            'name' => $request->name,
        ]);

        return response()->json([
            'tag' => new TagResource($tag),
        ], 201);
    }
}

