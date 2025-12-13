<?php

namespace App;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    title: "Talk Proposals API",
    description: "API documentation for the Talk Proposals system. This API allows speakers to submit talk proposals, reviewers to review them, and admins to manage the proposal status.",
    contact: new OA\Contact(
        email: "support@talkproposals.test"
    ),
    license: new OA\License(
        name: "MIT"
    )
)]
#[OA\Server(
    url: "http://api.talkproposals.test/api",
    description: "Local Development Server"
)]
#[OA\Server(
    url: "http://localhost:8000/api",
    description: "Local Development Server (Alternative)"
)]
#[OA\SecurityScheme(
    securityScheme: "sanctum",
    type: "apiKey",
    description: "Laravel Sanctum SPA authentication using session cookies. To authenticate: 1) Log in via the frontend application at http://talkproposals.test/login, 2) The session cookie will be automatically sent with requests from the same browser. Note: Swagger UI cannot manually set session cookies - you must authenticate via the frontend first.",
    name: "laravel_session",
    in: "cookie"
)]
#[OA\Tag(
    name: "Authentication",
    description: "User authentication and registration endpoints"
)]
#[OA\Tag(
    name: "Proposals",
    description: "Talk proposal management endpoints"
)]
#[OA\Tag(
    name: "Reviews",
    description: "Proposal review endpoints"
)]
#[OA\Tag(
    name: "Tags",
    description: "Tag management endpoints"
)]
#[OA\Tag(
    name: "Admin",
    description: "Admin-only endpoints for managing proposals"
)]
#[OA\Schema(
    schema: "User",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "name", type: "string", example: "John Doe"),
        new OA\Property(property: "email", type: "string", format: "email", example: "john@example.com"),
        new OA\Property(property: "role", type: "string", enum: ["speaker", "reviewer", "admin"], example: "speaker"),
    ],
    type: "object"
)]
#[OA\Schema(
    schema: "Proposal",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "title", type: "string", example: "Introduction to Laravel"),
        new OA\Property(property: "description", type: "string", example: "A comprehensive guide to Laravel framework"),
        new OA\Property(property: "file_path", type: "string", example: "/proposals/1/download", nullable: true),
        new OA\Property(property: "status", type: "string", enum: ["pending", "approved", "rejected"], example: "pending"),
        new OA\Property(property: "user", ref: "#/components/schemas/User"),
        new OA\Property(property: "tags", type: "array", items: new OA\Items(ref: "#/components/schemas/Tag")),
        new OA\Property(property: "average_rating", type: "number", format: "float", example: 4.5, nullable: true),
        new OA\Property(property: "reviews_count", type: "integer", example: 10),
        new OA\Property(property: "created_at", type: "string", format: "date-time", example: "2024-01-01T00:00:00Z"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time", example: "2024-01-01T00:00:00Z"),
    ],
    type: "object"
)]
#[OA\Schema(
    schema: "Tag",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "name", type: "string", example: "Technology"),
    ],
    type: "object"
)]
#[OA\Schema(
    schema: "Review",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "rating", type: "integer", enum: [1, 2, 3, 4, 5, 10], example: 5),
        new OA\Property(property: "comment", type: "string", example: "Great proposal!", nullable: true),
        new OA\Property(property: "reviewer", ref: "#/components/schemas/User"),
        new OA\Property(property: "created_at", type: "string", format: "date-time", example: "2024-01-01T00:00:00Z"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time", example: "2024-01-01T00:00:00Z"),
    ],
    type: "object"
)]
#[OA\Schema(
    schema: "ApiResponse",
    properties: [
        new OA\Property(property: "status", type: "string", enum: ["success", "error"], example: "success"),
        new OA\Property(property: "message", type: "string", example: "Operation successful"),
        new OA\Property(property: "data", type: "object", nullable: true),
        new OA\Property(property: "errors", type: "object", nullable: true),
    ],
    type: "object"
)]
class OpenApi
{
}

