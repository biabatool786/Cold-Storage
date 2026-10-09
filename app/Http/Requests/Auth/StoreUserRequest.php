<?php

namespace App\Http\Requests\Auth;

use App\Enums\RoleName;
use App\Http\Requests\TenantFormRequest;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends TenantFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => ['required', Rule::enum(RoleName::class), function (string $attribute, mixed $value, \Closure $fail): void {
                $actor = $this->user();
                $role = RoleName::tryFrom((string) $value);
                $tenantId = $actor?->isSuperAdmin() ? null : $actor?->tenant_id;

                if (! $actor || ! $role || ! $role->canBeAssignedTo($tenantId !== null ? (int) $tenantId : null, $actor)) {
                    $fail('You cannot assign that role.');
                }
            }],
        ];
    }
}
