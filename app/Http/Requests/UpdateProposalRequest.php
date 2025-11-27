<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProposalRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string'],
            'file' => ['sometimes', 'nullable', 'file', 'mimes:pdf', 'max:4096'], // 4MB max, optional
            'tags' => ['sometimes', 'nullable', 'array'], // Tags are optional
            'tags.*' => ['required', 'string', 'max:255'],
        ];
    }
}

