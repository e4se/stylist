<?php

namespace App\Http\Requests\Items;

use App\Models\Item;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\File;

class UpdateItemRequest extends FormRequest
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
        $item = $this->route('item');

        if (! $item instanceof Item) {
            $item = Item::query()->find($item);
        }

        return $item instanceof Item && Gate::allows('update', $item);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'main_upload' => ['nullable', File::image()->max(self::MAIN_UPLOAD_MAX_KILOBYTES)],
        ];
    }
}
