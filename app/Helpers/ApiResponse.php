<?php

namespace App\Helpers;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;

class ApiResponse
{
    /**
     * Return a successful JSON response.
     */
    public static function success(string $message, mixed $data = null, int $statusCode = 200): JsonResponse
    {
        $response = [
            'status' => 'success',
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return Response::json($response, $statusCode);
    }

    /**
     * Return an error JSON response.
     */
    public static function error(string $message, int $statusCode = 400, mixed $errors = null): JsonResponse
    {
        $response = [
            'status' => 'error',
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return Response::json($response, $statusCode);
    }

    /**
     * Return a validation error JSON response.
     */
    public static function validationError(Validator|array $errors, string $message = 'Validation failed'): JsonResponse
    {
        if ($errors instanceof Validator) {
            $errors = $errors->errors();
        }

        return Response::json([
            'status' => 'error',
            'message' => $message,
            'errors' => $errors,
        ], 422);
    }
}
