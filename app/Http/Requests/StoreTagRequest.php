<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Constants\ValidationConstants;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for storing a new tag.
 */
class StoreTagRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:'.ValidationConstants::MAX_TAG_LENGTH,
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
            'name.required' => 'The tag name is required.',
            'name.string' => 'The tag name must be a valid text.',
            'name.max' => 'The tag name cannot exceed '.ValidationConstants::MAX_TAG_LENGTH.' characters.',
        ];
    }
}
