<?php

namespace App\Models\Builders;

use App\Exceptions\ImmutableRecordException;
use Illuminate\Database\Eloquent\Builder;

class ImmutableQueryBuilder extends Builder
{
    public function update(array $values)
    {
        throw new ImmutableRecordException('This record cannot be edited.');
    }

    public function delete()
    {
        throw new ImmutableRecordException('This record cannot be deleted.');
    }

    public function upsert(array $values, $uniqueBy, $update = null)
    {
        throw new ImmutableRecordException('This record cannot be edited.');
    }
}
