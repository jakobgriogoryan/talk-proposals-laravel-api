<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Constants\FileConstants;
use App\Constants\ValidationConstants;
use App\Models\Proposal;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for storing a new proposal.
 */
class StoreProposalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Proposal::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => [
                'required',
                'string',
                'max:'.ValidationConstants::MAX_TITLE_LENGTH,
            ],
            'description' => [
                'required',
                'string',
            ],
            'file' => [
                'nullable',
                'file',
                'mimes:'.implode(',', FileConstants::ALLOWED_EXTENSIONS),
                'max:'.FileConstants::MAX_FILE_SIZE_KB,
            ],
            'tags' => [
                'nullable',
                'array',
            ],
            'tags.*' => [
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
            'title.required' => 'The proposal title is required.',
            'title.string' => 'The proposal title must be a valid text.',
            'title.max' => 'The proposal title cannot exceed '.ValidationConstants::MAX_TITLE_LENGTH.' characters.',
            'description.required' => 'The proposal description is required.',
            'description.string' => 'The proposal description must be a valid text.',
            'file.file' => 'The uploaded file must be a valid file.',
            'file.mimes' => 'The file must be a PDF document.',
            'file.max' => 'The file size must not exceed '.(FileConstants::MAX_FILE_SIZE_KB / 1024).'MB.',
            'tags.array' => 'Tags must be provided as an array.',
            'tags.*.required' => 'Each tag is required.',
            'tags.*.string' => 'Each tag must be a valid text.',
            'tags.*.max' => 'Each tag cannot exceed '.ValidationConstants::MAX_TAG_LENGTH.' characters.',
        ];
    }
}
