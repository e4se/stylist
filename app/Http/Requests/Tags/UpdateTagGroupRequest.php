<?php

namespace App\Http\Requests\Tags;

use App\Models\TagGroup;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class UpdateTagGroupRequest extends FormRequest
{
    /**
     * Maximum accepted tag group name length.
     */
    public const int NAME_MAX_CHARACTERS = StoreTagGroupRequest::NAME_MAX_CHARACTERS;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $tagGroup = $this->tagGroup();

        return $tagGroup instanceof TagGroup && Gate::allows('update', $tagGroup);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:'.self::NAME_MAX_CHARACTERS, $this->uniqueNameRule()],
        ];
    }

    private function uniqueNameRule(): Unique
    {
        $rule = Rule::unique(TagGroup::class, 'name')
            ->where(fn (Builder $query): Builder => $query->where('user_id', $this->user()?->getAuthIdentifier()));

        $tagGroup = $this->tagGroup();

        if ($tagGroup instanceof TagGroup) {
            $rule->ignore($tagGroup);
        }

        return $rule;
    }

    private function tagGroup(): ?TagGroup
    {
        $tagGroup = $this->route('tagGroup') ?? $this->route('tag_group');

        if ($tagGroup instanceof TagGroup) {
            return $tagGroup;
        }

        if (is_string($tagGroup) && $tagGroup !== '') {
            return TagGroup::query()->find($tagGroup);
        }

        return null;
    }
}
