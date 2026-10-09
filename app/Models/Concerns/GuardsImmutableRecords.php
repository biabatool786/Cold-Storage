<?php

namespace App\Models\Concerns;

use App\Exceptions\ImmutableRecordException;
use App\Models\Builders\ImmutableQueryBuilder;
use Illuminate\Database\Eloquent\Builder;

trait GuardsImmutableRecords
{
    public static function bootGuardsImmutableRecords(): void
    {
        static::updating(function (): void {
            throw new ImmutableRecordException('This record cannot be edited.');
        });

        static::deleting(function (): void {
            throw new ImmutableRecordException('This record cannot be deleted.');
        });
    }

    /**
     * Bulk updates and deletes skip model events, so the query builder is blocked too.
     */
    public function newEloquentBuilder($query): Builder
    {
        return new ImmutableQueryBuilder($query);
    }
}
