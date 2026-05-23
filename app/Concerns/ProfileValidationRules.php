<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null, bool $includeLocale = false): array
    {
        $rules = [
            'name' => $this->nameRules(),
            'email' => $this->emailRules($userId),
        ];

        if ($includeLocale) {
            $rules['locale'] = $this->localeRules();
        }

        return $rules;
    }

    /**
     * Get the validation rules used to validate user names.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate user emails.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }

    /**
     * Get the validation rules used to validate user locales.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function localeRules(): array
    {
        $supportedLocales = config('app.supported_locales', ['en']);

        if (! is_array($supportedLocales)) {
            $supportedLocales = ['en'];
        }

        return ['required', 'string', Rule::in($supportedLocales)];
    }
}
