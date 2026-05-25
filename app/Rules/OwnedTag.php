<?php

namespace App\Rules;

use App\Models\Tag;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;
use Illuminate\Translation\PotentiallyTranslatedString;

class OwnedTag implements ValidationRule
{
    public function __construct(private readonly ?User $user) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $this->user === null || ! Str::isUuid($value)) {
            return;
        }

        $exists = Tag::query()
            ->whereKey($value)
            ->whereIn('tag_group_id', $this->user->tagGroups()->select('id'))
            ->exists();

        if (! $exists) {
            $fail('validation.exists')->translate();
        }
    }
}
