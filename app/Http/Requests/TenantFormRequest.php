<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class TenantFormRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->offsetUnset('tenant_id');

        if ($this->isJson()) {
            $payload = $this->json()->all();
            unset($payload['tenant_id']);
            $this->json()->replace($payload);
        }
    }

    /**
     * @param  mixed  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        if ($key !== null) {
            return $validated;
        }

        if (is_array($validated)) {
            unset($validated['tenant_id']);
        }

        return $validated;
    }
}
