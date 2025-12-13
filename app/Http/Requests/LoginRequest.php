<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Constants\ValidationConstants;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for user login.
 */
class LoginRequest extends FormRequest
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
            'email' => [
                'required',
                'email',
                'max:'.ValidationConstants::MAX_EMAIL_LENGTH,
            ],
            'password' => [
                'required',
                'string',
            ],
            'remember' => [
                'sometimes',
                'boolean',
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
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please provide a valid email address.',
            'email.max' => 'Your email address cannot exceed '.ValidationConstants::MAX_EMAIL_LENGTH.' characters.',
            'password.required' => 'Please enter your password.',
            'password.string' => 'The password must be a valid text.',
        ];
    }
}
