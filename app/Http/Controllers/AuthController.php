<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Helpers\ApiResponse;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Controller for authentication.
 */
class AuthController extends Controller
{
    /**
     * Register a new user.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $role = UserRole::from($request->string('role')->toString());

            $user = User::create([
                'name' => $request->string('name')->toString(),
                'email' => $request->string('email')->toString(),
                'password' => Hash::make($request->string('password')->toString()),
                'role' => $role->value,
            ]);

            // Login the user for SPA authentication
            Auth::guard('web')->login($user);

            DB::commit();

            return ApiResponse::success(
                'Registration successful',
                ['user' => new UserResource($user)],
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error registering user', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Failed to register user', 500);
        }
    }

    /**
     * Login user.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            if (! Auth::guard('web')->attempt($request->only('email', 'password'), $request->boolean('remember'))) {
                return ApiResponse::error('Invalid credentials', 401);
            }

            // Regenerate session if available
            // The EnsureFrontendRequestsAreStateful middleware should have initialized it
            if ($request->hasSession()) {
                $request->session()->regenerate();
            } elseif (session()->isStarted()) {
                session()->regenerate();
            }

            $user = Auth::guard('web')->user();

            if (! $user) {
                return ApiResponse::error('Authentication failed', 401);
            }

            return ApiResponse::success(
                'Login successful',
                ['user' => new UserResource($user)]
            );
        } catch (\Exception $e) {
            Log::error('Error logging in user', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Failed to login', 500);
        }
    }

    /**
     * Get authenticated user.
     */
    public function user(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (! $user) {
                return ApiResponse::error('Unauthenticated', 401);
            }

            return ApiResponse::success(
                'User retrieved successfully',
                ['user' => new UserResource($user)]
            );
        } catch (\Exception $e) {
            Log::error('Error retrieving user', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Failed to retrieve user', 500);
        }
    }

    /**
     * Logout user.
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            Auth::guard('web')->logout();

            // Invalidate session if available
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            } elseif (session()->isStarted()) {
                session()->invalidate();
                session()->regenerateToken();
            }

            return ApiResponse::success('Logout successful');
        } catch (\Exception $e) {
            Log::error('Error logging out user', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Failed to logout', 500);
        }
    }
}
