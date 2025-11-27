<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ProposalStatus;
use App\Models\Proposal;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for updating a proposal status.
 */
class UpdateProposalStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var Proposal $proposal */
        $proposal = $this->route('proposal');

        return $this->user()->can('updateStatus', $proposal);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                'in:'.implode(',', ProposalStatus::values()),
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
            'status.required' => 'Please select a status for this proposal.',
            'status.in' => 'The status must be one of: '.implode(', ', ProposalStatus::values()).'.',
        ];
    }
}
