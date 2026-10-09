<?php

namespace App\Services\Authorization;

use App\Enums\RoleName;
use App\Models\User;

class FieldAccess
{
    /**
     * @var list<string>
     */
    private const GATEKEEPER_HIDDEN = [
        'rate',
        'rates',
        'balance',
        'balances',
        'invoice_amount',
        'ledger',
        'customer_financials',
        'credit_limit',
    ];

    /**
     * @var list<string>
     */
    private const SUPERVISOR_HIDDEN = [
        'invoice_amount',
        'ledger',
    ];

    public function denies(User $user, string $field): bool
    {
        return in_array($field, $this->hiddenFields($user), true);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function filter(User $user, array $payload): array
    {
        $hidden = $this->hiddenFields($user);

        foreach ($hidden as $field) {
            unset($payload[$field]);
        }

        return $payload;
    }

    /**
     * @return list<string>
     */
    public function hiddenFields(User $user): array
    {
        if ($user->hasRole(RoleName::Gatekeeper)) {
            return self::GATEKEEPER_HIDDEN;
        }

        if ($user->hasRole(RoleName::Supervisor)) {
            return self::SUPERVISOR_HIDDEN;
        }

        return [];
    }
}
