<?php

namespace App\Http\Requests;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Validation\Rule;

class UpdateTenantStatusRequest extends TenantFormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->route('tenant');

        return $tenant instanceof Tenant
            && ($this->user()?->isSuperAdmin() ?? false)
            && ($this->user()?->can('update', $tenant) ?? false);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(TenantStatus::class)],
        ];
    }
}
