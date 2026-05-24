<?php

namespace App\Http\Requests\Uploads;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\File;

class StoreUploadRequest extends FormRequest
{
    /**
     * Maximum accepted upload size in kilobytes.
     */
    public const int FILE_MAX_KILOBYTES = 10 * 1024;

    /**
     * Maximum accepted original filename length.
     */
    public const int FILE_NAME_MAX_CHARACTERS = 255;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                File::image()->max(self::FILE_MAX_KILOBYTES),
                $this->uploadFilenameMaxRule(),
            ],
        ];
    }

    private function uploadFilenameMaxRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! $value instanceof UploadedFile) {
                return;
            }

            if (mb_strlen($value->getClientOriginalName()) <= self::FILE_NAME_MAX_CHARACTERS) {
                return;
            }

            $fail('validation.upload_filename_max')->translate([
                'max' => self::FILE_NAME_MAX_CHARACTERS,
            ]);
        };
    }
}
