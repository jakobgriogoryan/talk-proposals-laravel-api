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
use OpenApi\Attributes as OA;

/**
 * Controller for authentication.
 */
#[OA\Tag(name: 'Authentication')]
class AuthController extends Controller
{
    /**
     * Register a new user.
     */
    #[OA\Post(
        path: '/register',
        summary: 'Register a new user',
        description: 'Creates a new user account with the provided information. The user will be automatically logged in after registration.',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password', 'password_confirmation', 'role'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'John Doe', description: "User's full name"),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com', description: "User's email address"),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123', description: "User's password"),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'password123', description: 'Password confirmation'),
                    new OA\Property(property: 'role', type: 'string', enum: ['speaker', 'reviewer'], example: 'speaker', description: 'User role (speaker or reviewer)'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Registration successful',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'message', type: 'string', example: 'Registration successful'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'user',
                                    ref: '#/components/schemas/User'
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
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
    #[OA\Post(
        path: '/login',
        summary: 'Login user',
        description: 'Authenticates a user with email and password. Uses Laravel Sanctum for SPA authentication with session cookies.',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123'),
                    new OA\Property(property: 'remember', type: 'boolean', example: false, description: 'Remember user session'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Login successful',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'message', type: 'string', example: 'Login successful'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'user',
                                    ref: '#/components/schemas/User'
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Invalid credentials'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
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
    #[OA\Get(
        path: '/user',
        summary: 'Get authenticated user',
        description: "Returns the currently authenticated user's information.",
        tags: ['Authentication'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'message', type: 'string', example: 'User retrieved successfully'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'user',
                                    ref: '#/components/schemas/User'
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
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
    #[OA\Post(
        path: '/logout',
        summary: 'Logout user',
        description: 'Logs out the currently authenticated user and invalidates the session.',
        tags: ['Authentication'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Logout successful',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'message', type: 'string', example: 'Logout successful'),
                    ]
                )
            ),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
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
