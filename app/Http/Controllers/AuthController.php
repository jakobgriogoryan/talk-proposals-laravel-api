<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Helpers\ApiResponse;
use App\Helpers\CacheHelper;
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
#[OA\Tag(name: "Authentication")]
class AuthController extends Controller
{
    /**
     * Register a new user.
     */
    #[OA\Post(
        path: "/register",
        description: "Creates a new user account with the provided information. The user will be automatically logged in after registration.",
        summary: "Register a new user",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "email", "password", "password_confirmation", "role"],
                properties: [
                    new OA\Property(property: "name", description: "User's full name", type: "string", example: "John Doe"),
                    new OA\Property(property: "email", description: "User's email address", type: "string", format: "email", example: "john@example.com"),
                    new OA\Property(property: "password", description: "User's password", type: "string", format: "password", example: "password123"),
                    new OA\Property(property: "password_confirmation", description: "Password confirmation", type: "string", format: "password", example: "password123"),
                    new OA\Property(property: "role", description: "User role (speaker or reviewer)", type: "string", enum: ["speaker", "reviewer"], example: "speaker"),
                ]
            )
        ),
        tags: ["Authentication"],
        responses: [
            new OA\Response(
                response: 201,
                description: "Registration successful",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "message", type: "string", example: "Registration successful"),
                        new OA\Property(
                            property: "data",
                            properties: [
                                new OA\Property(
                                    property: "user",
                                    ref: "#/components/schemas/User"
                                ),
                            ],
                            type: "object"
                        ),
                    ]
                )
            ),
            new OA\Response(response: 422, description: "Validation error"),
            new OA\Response(response: 500, description: "Server error"),
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

            // Cache the newly registered user
            CacheHelper::rememberUser(fn () => $user, $user->id);

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
        path: "/login",
        description: "Authenticates a user with email and password. Uses Laravel Sanctum for SPA authentication with session cookies.",
        summary: "Login user",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email", "password"],
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email", example: "john@example.com"),
                    new OA\Property(property: "password", type: "string", format: "password", example: "password123"),
                    new OA\Property(property: "remember", description: "Remember user session", type: "boolean", example: false),
                ]
            )
        ),
        tags: ["Authentication"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Login successful",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "message", type: "string", example: "Login successful"),
                        new OA\Property(
                            property: "data",
                            properties: [
                                new OA\Property(
                                    property: "user",
                                    ref: "#/components/schemas/User"
                                ),
                            ],
                            type: "object"
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Invalid credentials"),
            new OA\Response(response: 500, description: "Server error"),
        ]
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            if (! Auth::guard('web')
                ->attempt($request->only('email', 'password'), $request->boolean('remember'))
            ) {
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

            // Cache the logged-in user
            CacheHelper::rememberUser(fn () => $user, $user->id);

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
        path: "/user",
        description: "Returns the currently authenticated user's information.",
        summary: "Get authenticated user",
        security: [["sanctum" => []]],
        tags: ["Authentication"],
        responses: [
            new OA\Response(
                response: 200,
                description: "User retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "message", type: "string", example: "User retrieved successfully"),
                        new OA\Property(
                            property: "data",
                            properties: [
                                new OA\Property(
                                    property: "user",
                                    ref: "#/components/schemas/User"
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
    public function user(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (! $user) {
                return ApiResponse::error('Unauthenticated', 401);
            }

            // Use cache for user data (5 minutes TTL)
            $cachedUser = CacheHelper::rememberUser(function () use ($user) {
                return $user->fresh(); // Refresh to get latest data
            }, $user->id);

            return ApiResponse::success(
                'User retrieved successfully',
                ['user' => new UserResource($cachedUser)]
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
        path: "/logout",
        description: "Logs out the currently authenticated user and invalidates the session.",
        summary: "Logout user",
        security: [["sanctum" => []]],
        tags: ["Authentication"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Logout successful",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "message", type: "string", example: "Logout successful"),
                    ]
                )
            ),
            new OA\Response(response: 500, description: "Server error"),
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

            // Invalidate user cache on logout
            if ($request->user()) {
                CacheHelper::forgetUser($request->user()->id);
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
