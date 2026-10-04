<?php

declare(strict_types=1);

namespace App\Http\Requests;

/** Admin proposal listing shares proposal filters, but keeps admin authorization. */
class IndexAdminProposalRequest extends IndexProposalRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'user_id.integer' => 'The user ID must be an integer.',
            'user_id.exists' => 'The selected user does not exist.',
        ];
    }
}
