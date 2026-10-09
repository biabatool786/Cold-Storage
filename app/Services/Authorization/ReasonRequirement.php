<?php

namespace App\Services\Authorization;

use Illuminate\Validation\ValidationException;

class ReasonRequirement
{
    /**
     * @var list<string>
     */
    public const ACTIONS = [
        'edit_after_approval',
        'override',
        'reversal',
        'delete',
        'approval_override',
    ];

    public function requires(?string $action): bool
    {
        return $action !== null && in_array($action, self::ACTIONS, true);
    }

    public function assert(string $action, ?string $reason): void
    {
        if ($this->requires($action) && blank($reason)) {
            throw ValidationException::withMessages([
                'reason' => 'A reason is required for this action.',
            ]);
        }
    }
}
