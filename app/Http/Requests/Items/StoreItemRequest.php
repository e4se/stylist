<?php

namespace App\Http\Requests\Items;

use App\Models\Item;
use App\Models\Upload;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreItemRequest extends FormRequest
{
    /**
     * Maximum accepted main upload size in kilobytes.
     */
    public const int MAIN_UPLOAD_MAX_KILOBYTES = 10 * 1024;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('create', Item::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'main_upload' => ['nullable', File::image()->max(self::MAIN_UPLOAD_MAX_KILOBYTES)],
            'main_upload_id' => [
                'nullable',
                'uuid',
                Rule::exists((new Upload)->getTable(), 'id')
                    ->where(fn (Builder $query): Builder => $query->where('user_id', $this->user()?->getAuthIdentifier())),
            ],
        ];
    }
}
