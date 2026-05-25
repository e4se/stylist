<?php

namespace App\Http\Requests\Tags;

use App\Models\TagGroup;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class StoreTagGroupRequest extends FormRequest
{
    /**
     * Maximum accepted tag group name length.
     */
    public const int NAME_MAX_CHARACTERS = 255;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('create', TagGroup::class);
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
        return Rule::unique(TagGroup::class, 'name')
            ->where(fn (Builder $query): Builder => $query->where('user_id', $this->user()?->getAuthIdentifier()));
    }
}
