<?php

namespace App\Services\Authorization;

use Illuminate\Auth\Access\AuthorizationException;

class FinancialDocumentGuard
{
    public function isLocked(string $state): bool
    {
        return in_array(strtolower($state), ['posted', 'locked'], true);
    }

    public function assertMutable(string $state): void
    {
        if ($this->isLocked($state)) {
            throw new AuthorizationException('Posted financial documents are locked. Use a credit note, debit note, or reversal.');
        }
    }
}
