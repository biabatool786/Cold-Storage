<?php

namespace App\Exceptions;

use RuntimeException;

class ImmutableRecordException extends RuntimeException
{
    public function __construct(string $message = 'This record cannot be edited or deleted.')
    {
        parent::__construct($message);
    }
}
