# API Documentation Guide

This project uses OpenAPI (Swagger) for API documentation. The documentation is automatically generated from code annotations.

## Accessing the Documentation

Once the Laravel application is running, you can access the API documentation at:

**Important:** Access the documentation from the **API domain**, not the frontend domain:

**Local Development:**
- ✅ http://api.talkproposals.test/api/documentation (Correct - use this)
- ✅ http://localhost:8000/api/documentation (Correct - use this)
- ❌ http://talkproposals.test/api/documentation (Will cause CORS errors)

**Note:** If you access the documentation from the frontend domain (`talkproposals.test`), you may encounter CORS errors. Always use the API domain (`api.talkproposals.test` or `localhost:8000`).

## Generating Documentation

The documentation is automatically generated from OpenAPI annotations in the code. To regenerate the documentation:

```bash
cd talk-proposals-api
php artisan l5-swagger:generate
```

## Documentation Structure

The API documentation includes:

### Authentication Endpoints
- `POST /api/register` - Register a new user
- `POST /api/login` - Login user
- `GET /api/user` - Get authenticated user
- `POST /api/logout` - Logout user

### Proposal Endpoints
- `GET /api/proposals` - List proposals (filtered by user role)
- `POST /api/proposals` - Create a new proposal
- `GET /api/proposals/{id}` - Get a specific proposal
- `PUT /api/proposals/{id}` - Update a proposal
- `DELETE /api/proposals/{id}` - Delete a proposal
- `GET /api/proposals/top-rated` - Get top-rated proposals
- `GET /api/proposals/{id}/download` - Download proposal PDF

### Review Endpoints
- `GET /api/proposals/{id}/reviews` - Get reviews for a proposal
- `POST /api/proposals/{id}/reviews` - Create a review
- `GET /api/proposals/{id}/reviews/{reviewId}` - Get a specific review
- `PUT /api/proposals/{id}/reviews/{reviewId}` - Update a review
- `GET /api/reviews/rating-options` - Get available rating options

### Tag Endpoints
- `GET /api/tags` - List all tags
- `POST /api/tags` - Create a new tag

### Admin Endpoints
- `GET /api/admin/proposals` - List all proposals (admin only)
- `PATCH /api/admin/proposals/{id}/status` - Update proposal status (admin only)

### Reviewer Endpoints
- `GET /api/review/proposals` - List all proposals for review (reviewer only)

## Authentication

The API uses Laravel Sanctum for SPA authentication. Authentication is handled via session cookies.

To authenticate in Swagger UI:
1. Use the `/api/login` endpoint to log in
2. The session cookie will be automatically stored
3. Subsequent requests will be authenticated

## Response Format

All API responses follow a consistent format:

### Success Response
```json
{
  "status": "success",
  "message": "Operation successful",
  "data": {
    // Response data here
  }
}
```

### Error Response
```json
{
  "status": "error",
  "message": "Error message",
  "errors": {
    // Validation errors (if applicable)
  }
}
```

## Testing the API

You can test the API directly from the Swagger UI:

1. Navigate to the documentation URL
2. Click on any endpoint to expand it
3. Click "Try it out"
4. Fill in the required parameters
5. Click "Execute" to send the request
6. View the response below

## Adding New Endpoints

To document a new endpoint:

1. Add OpenAPI annotations to your controller method using PHP 8 attributes:

```php
use OpenApi\Attributes as OA;

#[OA\Post(
    path: "/api/your-endpoint",
    summary: "Your endpoint summary",
    description: "Detailed description",
    tags: ["YourTag"],
    security: [["sanctum" => []]], // If authentication required
    requestBody: new OA\RequestBody(...),
    responses: [
        new OA\Response(response: 200, description: "Success"),
        new OA\Response(response: 401, description: "Unauthorized"),
    ]
)]
public function yourMethod(Request $request): JsonResponse
{
    // Your code
}
```

2. Regenerate the documentation:
```bash
php artisan l5-swagger:generate
```

## Schema Definitions

Common schemas are defined in `app/OpenApi.php`:
- `User` - User object schema
- `Proposal` - Proposal object schema
- `Tag` - Tag object schema
- `Review` - Review object schema
- `ApiResponse` - Standard API response format

## Troubleshooting

### Documentation not updating
- Clear the cache: `php artisan config:clear`
- Regenerate docs: `php artisan l5-swagger:generate`

### Routes not appearing
- Ensure annotations are properly formatted
- Check that the controller is in the `app` directory (configured in `config/l5-swagger.php`)

### Authentication issues in Swagger UI
- Ensure you're logged in via the `/api/login` endpoint first
- Check that session cookies are enabled in your browser

## Additional Resources

- [OpenAPI Specification](https://swagger.io/specification/)
- [L5-Swagger Documentation](https://github.com/DarkaOnLine/L5-Swagger)
- [Swagger UI](https://swagger.io/tools/swagger-ui/)

