<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;

class BelongsToCurrentTenant implements ValidationRule
{
    /**
     * @param  class-string<Model>  $modelClass
     */
    public function __construct(private readonly string $modelClass) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_numeric($value)) {
            $fail('The selected :attribute is invalid.');

            return;
        }

        $exists = $this->modelClass::query()->whereKey($value)->exists();

        if (! $exists) {
            $fail('The selected :attribute is invalid.');
        }
    }
}
