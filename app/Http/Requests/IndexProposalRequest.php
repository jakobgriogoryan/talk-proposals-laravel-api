<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Constants\PaginationConstants;
use App\Enums\ProposalStatus;
use App\Models\Proposal;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request for listing proposals with filters.
 */
class IndexProposalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Proposal::class);
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
                'max:255',
            ],
            'tags' => [
                'sometimes',
                'nullable',
                function ($attribute, $value, $fail) {
                    // Accept both array and comma-separated string
                    if (! is_array($value) && ! is_string($value)) {
                        $fail('The tags must be an array or comma-separated string.');
                    }
                },
            ],
            'status' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in(ProposalStatus::values()),
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
                'min:'.PaginationConstants::MIN_PER_PAGE,
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
            'search.max' => 'The search query cannot exceed 255 characters.',
            'tags.array' => 'Tags must be provided as an array or comma-separated string.',
            'status.in' => 'The status must be one of: '.implode(', ', ProposalStatus::values()).'.',
            'page.integer' => 'The page number must be an integer.',
            'page.min' => 'The page number must be at least 1.',
            'per_page.integer' => 'Items per page must be an integer.',
            'per_page.min' => 'Items per page must be at least '.PaginationConstants::MIN_PER_PAGE.'.',
            'per_page.max' => 'Items per page cannot exceed '.PaginationConstants::MAX_PER_PAGE.'.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert comma-separated tags string to array for validation
        if ($this->has('tags') && is_string($this->tags)) {
            $this->merge([
                'tags' => explode(',', $this->tags),
            ]);
        }
    }
}

