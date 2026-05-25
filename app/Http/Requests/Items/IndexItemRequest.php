<?php

namespace App\Http\Requests\Items;

use App\Concerns\ItemTagValidationRules;
use App\Models\Item;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class IndexItemRequest extends FormRequest
{
    use ItemTagValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('viewAny', Item::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->itemTagRules($this->user());
    }
}
