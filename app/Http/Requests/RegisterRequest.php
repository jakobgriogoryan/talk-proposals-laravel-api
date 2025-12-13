<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Constants\ValidationConstants;
use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Form request for user registration.
 */
class RegisterRequest extends FormRequest
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
                'max:'.ValidationConstants::MAX_NAME_LENGTH,
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:'.ValidationConstants::MAX_EMAIL_LENGTH,
                'unique:users',
            ],
            'password' => [
                'required',
                'confirmed',
                Password::defaults(),
            ],
            'role' => [
                'required',
                'string',
                'in:'.implode(',', UserRole::registrationRoles()),
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
            'name.required' => 'Your name is required.',
            'name.string' => 'Your name must be a valid text.',
            'name.max' => 'Your name cannot exceed '.ValidationConstants::MAX_NAME_LENGTH.' characters.',
            'email.required' => 'Your email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.max' => 'Your email address cannot exceed '.ValidationConstants::MAX_EMAIL_LENGTH.' characters.',
            'email.unique' => 'This email address is already registered. Please use a different email or log in.',
            'password.required' => 'A password is required.',
            'password.confirmed' => 'The password confirmation does not match.',
            'role.required' => 'Please select your role.',
            'role.in' => 'Please select either Speaker or Reviewer role.',
        ];
    }
}
