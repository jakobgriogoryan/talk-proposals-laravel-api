<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Constants\PaginationConstants;
use App\Constants\ValidationConstants;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for listing tags with search and pagination.
 */
class IndexTagRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by route middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => [
                'sometimes',
                'nullable',
                'string',
                'max:'.ValidationConstants::MAX_SEARCH_LENGTH,
            ],
            'page' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
            ],
            'per_page' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
                'max:'.PaginationConstants::MAX_PER_PAGE,
            ],
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'search.string' => 'The search query must be a valid string.',
            'search.max' => 'The search query cannot exceed '.ValidationConstants::MAX_SEARCH_LENGTH.' characters.',
            'page.integer' => 'The page number must be an integer.',
            'page.min' => 'The page number must be at least 1.',
            'per_page.integer' => 'Items per page must be an integer.',
            'per_page.min' => 'Items per page must be at least 1.',
            'per_page.max' => 'Items per page cannot exceed '.PaginationConstants::MAX_PER_PAGE.'.',
        ];
    }
}
