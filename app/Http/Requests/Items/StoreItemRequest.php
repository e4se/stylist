<?php

namespace App\Http\Requests\Items;

use App\Concerns\ItemTagValidationRules;
use App\Models\Item;
use App\Models\Upload;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreItemRequest extends FormRequest
{
    use ItemTagValidationRules;

    /**
     * Maximum accepted main upload size in kilobytes.
     */
    public const int MAIN_UPLOAD_MAX_KILOBYTES = 10 * 1024;

    /**
     * Maximum accepted wardrobe item name length.
     */
    public const int NAME_MAX_CHARACTERS = 255;

    /**
     * Maximum accepted main upload filename length.
     */
    public const int MAIN_UPLOAD_NAME_MAX_CHARACTERS = 255;

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
            'name' => ['required', 'string', 'max:'.self::NAME_MAX_CHARACTERS],
            'description' => ['nullable', 'string'],
            'main_upload' => [
                'nullable',
                File::image()->max(self::MAIN_UPLOAD_MAX_KILOBYTES),
                $this->uploadFilenameMaxRule(),
            ],
            'main_upload_id' => [
                'nullable',
                'uuid',
                Rule::exists((new Upload)->getTable(), 'id')
                    ->where(fn (Builder $query): Builder => $query->where('user_id', $this->user()?->getAuthIdentifier())),
            ],
            ...$this->itemTagRules($this->user()),
        ];
    }

    private function uploadFilenameMaxRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! $value instanceof UploadedFile) {
                return;
            }

            if (mb_strlen($value->getClientOriginalName()) <= self::MAIN_UPLOAD_NAME_MAX_CHARACTERS) {
                return;
            }

            $fail('validation.upload_filename_max')->translate([
                'max' => self::MAIN_UPLOAD_NAME_MAX_CHARACTERS,
            ]);
        };
    }
}
