<?php

namespace App\Http\Requests;

use App\Models\Tenant;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreTenantAdminRequest extends TenantFormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->isSuperAdmin() ?? false)
            && ($this->user()?->can('create', Tenant::class) ?? false);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }
}
