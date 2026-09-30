<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Constants\PaginationConstants;
use App\Models\Proposal;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for retrieving top-rated proposals.
 */
class TopRatedProposalRequest extends FormRequest
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
            'limit' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
                'max:'.PaginationConstants::MAX_TOP_RATED_LIMIT,
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
            'limit.integer' => 'The limit must be an integer.',
            'limit.min' => 'The limit must be at least 1.',
            'limit.max' => 'The limit cannot exceed '.PaginationConstants::MAX_TOP_RATED_LIMIT.'.',
        ];
    }
}

