<?php

namespace App\Concerns;

use App\Models\User;
use App\Rules\OwnedTag;
use Illuminate\Contracts\Validation\ValidationRule;

trait ItemTagValidationRules
{
    /**
     * Get the validation rules used to validate item tag IDs.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function itemTagRules(?User $user): array
    {
        return [
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => $this->itemTagIdRules($user),
        ];
    }

    /**
     * Get the validation rules used to validate a single item tag ID.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function itemTagIdRules(?User $user): array
    {
        return ['bail', 'required', 'uuid', 'distinct:strict', new OwnedTag($user)];
    }
}
