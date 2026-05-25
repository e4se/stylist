<?php

namespace App\Http\Requests\Tags;

use App\Models\Tag;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class UpdateTagRequest extends FormRequest
{
    /**
     * Maximum accepted tag name length.
     */
    public const int NAME_MAX_CHARACTERS = StoreTagRequest::NAME_MAX_CHARACTERS;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $tag = $this->tag();

        return $tag instanceof Tag && Gate::allows('update', $tag);
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

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => (string) __('validation.attributes.tag_name'),
        ];
    }

    private function uniqueNameRule(): Unique
    {
        $tag = $this->tag();

        $rule = Rule::unique(Tag::class, 'name')
            ->where(fn (Builder $query): Builder => $query->where('tag_group_id', $tag?->getAttribute('tag_group_id')));

        if ($tag instanceof Tag) {
            $rule->ignore($tag);
        }

        return $rule;
    }

    private function tag(): ?Tag
    {
        $tag = $this->route('tag');

        if ($tag instanceof Tag) {
            return $tag;
        }

        if (is_string($tag) && $tag !== '') {
            return Tag::query()->find($tag);
        }

        return null;
    }
}
