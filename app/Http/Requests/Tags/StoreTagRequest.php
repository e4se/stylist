<?php

namespace App\Http\Requests\Tags;

use App\Models\Tag;
use App\Models\TagGroup;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class StoreTagRequest extends FormRequest
{
    /**
     * Maximum accepted tag name length.
     */
    public const int NAME_MAX_CHARACTERS = 255;

    /**
     * Maximum accepted tag color length.
     */
    public const int COLOR_MAX_CHARACTERS = 7;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $tagGroup = $this->routeTagGroup();

        if ($tagGroup instanceof TagGroup) {
            return Gate::allows('create', [Tag::class, $tagGroup]);
        }

        return Gate::allows('create', Tag::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tag_group_id' => [
                'bail',
                'required',
                'uuid',
                Rule::exists(TagGroup::class, 'id')
                    ->where(fn (Builder $query): Builder => $query->where('user_id', $this->user()?->getAuthIdentifier())),
            ],
            'name' => ['required', 'string', 'max:'.self::NAME_MAX_CHARACTERS, $this->uniqueNameRule()],
            'color' => ['nullable', 'string', 'max:'.self::COLOR_MAX_CHARACTERS, 'hex_color'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'color' => (string) __('validation.attributes.tag_color'),
            'name' => (string) __('validation.attributes.tag_name'),
            'tag_group_id' => (string) __('validation.attributes.tag_group_id'),
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $tagGroup = $this->routeTagGroup();

        if ($tagGroup instanceof TagGroup && ! $this->has('tag_group_id')) {
            $this->merge([
                'tag_group_id' => (string) $tagGroup->getKey(),
            ]);
        }
    }

    private function uniqueNameRule(): Unique
    {
        $tagGroupId = $this->input('tag_group_id');

        return Rule::unique(Tag::class, 'name')
            ->where(fn (Builder $query): Builder => $query->where(
                'tag_group_id',
                is_string($tagGroupId) && Str::isUuid($tagGroupId) ? $tagGroupId : null,
            ));
    }

    private function routeTagGroup(): ?TagGroup
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
