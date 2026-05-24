<?php

namespace App\Http\Requests\Uploads;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreUploadRequest extends FormRequest
{
    /**
     * Maximum accepted upload size in kilobytes.
     */
    public const int FILE_MAX_KILOBYTES = 10 * 1024;

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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', File::image()->max(self::FILE_MAX_KILOBYTES)],
        ];
    }
}
