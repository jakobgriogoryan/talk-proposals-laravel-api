<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ReviewRating;
use App\Models\Review;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for storing a new review.
 */
class StoreReviewRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Review::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rating' => [
                'required',
                'integer',
                'in:'.implode(',', ReviewRating::values()),
            ],
            'comment' => [
                'nullable',
                'string',
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
            'rating.required' => 'Please select a rating for this proposal.',
            'rating.integer' => 'The rating must be a number.',
            'rating.in' => 'The rating must be one of: '.implode(', ', ReviewRating::values()).'.',
            'comment.string' => 'The comment must be a valid text.',
        ];
    }
}
