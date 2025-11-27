<?php

use App\Http\Controllers\AdminProposalController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProposalController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\TagController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public routes - login/register need sessions for Sanctum SPA
// These routes are handled by Sanctum's EnsureFrontendRequestsAreStateful middleware
// which enables sessions for API routes
Route::post('/register', [AuthController::class, 'register'])->name('register');;
Route::post('/login', [AuthController::class, 'login'])->name('login');

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Tags
    Route::get('/tags', [TagController::class, 'index']);
    Route::post('/tags', [TagController::class, 'store']);

    // Proposals (speaker routes)
    Route::apiResource('proposals', ProposalController::class);

    // Reviews
    Route::get('/proposals/{proposal}/reviews', [ReviewController::class, 'index']);
    Route::post('/proposals/{proposal}/reviews', [ReviewController::class, 'store']);
    Route::get('/proposals/{proposal}/reviews/{review}', [ReviewController::class, 'show']);

    // Reviewer routes - reviewers can see all proposals for review
    Route::prefix('review')->group(function () {
        Route::get('/proposals', function (Request $request) {
            if (! $request->user()->isReviewer()) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
            return app(ProposalController::class)->index($request);
        });
    });

    // Admin routes
    Route::prefix('admin')->group(function () {
        Route::get('/proposals', function (Request $request) {
            if (! $request->user()->isAdmin()) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
            return app(AdminProposalController::class)->index($request);
        });
        Route::patch('/proposals/{proposal}/status', function (Request $request, \App\Models\Proposal $proposal) {
            if (! $request->user()->isAdmin()) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
            return app(AdminProposalController::class)->updateStatus($request, $proposal);
        });
    });
});
