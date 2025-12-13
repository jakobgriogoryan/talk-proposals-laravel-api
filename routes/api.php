<?php

use App\Helpers\ApiResponse;
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
Route::post('/register', [AuthController::class, 'register'])
    ->middleware('throttle:auth')
    ->name('register');
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:auth')
    ->name('login');

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // Broadcasting authentication
    Route::post('/broadcasting/auth', function (Request $request) {
        return \Illuminate\Support\Facades\Broadcast::auth($request);
    });

    // Tags
    Route::get('/tags', [TagController::class, 'index']);
    Route::post('/tags', [TagController::class, 'store']);

    // Proposals (speaker routes)
    Route::get('/proposals/top-rated', [ProposalController::class, 'topRated']);
    Route::get('/proposals/{proposal}/download', [ProposalController::class, 'downloadFile'])->name('proposals.download');
    Route::post('/proposals', [ProposalController::class, 'store'])
        ->middleware('throttle:proposals,uploads');
    Route::get('/proposals', [ProposalController::class, 'index']);
    Route::get('/proposals/{proposal}', [ProposalController::class, 'show']);
    Route::put('/proposals/{proposal}', [ProposalController::class, 'update'])
        ->middleware('throttle:uploads');
    Route::patch('/proposals/{proposal}', [ProposalController::class, 'update'])
        ->middleware('throttle:uploads');
    Route::delete('/proposals/{proposal}', [ProposalController::class, 'destroy']);

    // Reviews
    Route::get('/reviews/rating-options', [ReviewController::class, 'ratingOptions']);
    Route::get('/proposals/{proposal}/reviews', [ReviewController::class, 'index']);
    Route::post('/proposals/{proposal}/reviews', [ReviewController::class, 'store']);
    Route::get('/proposals/{proposal}/reviews/{review}', [ReviewController::class, 'show']);
    Route::put('/proposals/{proposal}/reviews/{review}', [ReviewController::class, 'update']);

    // Reviewer routes - reviewers can see all proposals for review
    Route::prefix('review')->group(function () {
        Route::get('/proposals', [ProposalController::class, 'indexForReview']);
    });

    // Admin routes
    Route::prefix('admin')->group(function () {
        // Authorization is enforced in the controller via isAdmin()
        Route::get('/proposals', [AdminProposalController::class, 'index']);
        Route::patch('/proposals/{proposal}/status', [AdminProposalController::class, 'updateStatus']);
    });
});
