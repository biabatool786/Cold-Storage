<?php

namespace App\Http\Requests\Auth;

use App\Enums\RoleName;
use App\Http\Requests\TenantFormRequest;
use App\Models\User;
use Illuminate\Validation\Rule;

class UpdateUserRoleRequest extends TenantFormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User
            && ($this->user()?->can('assignRole', $target) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::enum(RoleName::class), function (string $attribute, mixed $value, \Closure $fail): void {
                $actor = $this->user();
                $target = $this->route('user');
                $role = RoleName::tryFrom((string) $value);
                $tenantId = $target instanceof User && $target->tenant_id !== null ? (int) $target->tenant_id : null;

                if (! $actor || ! $role || ! $role->canBeAssignedTo($tenantId, $actor)) {
                    $fail('You cannot assign that role.');
                }
            }],
        ];
    }
}
