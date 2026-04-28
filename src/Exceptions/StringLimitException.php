<?php

declare(strict_types=1);

namespace Stringhive\Exceptions;

use RuntimeException;

class StringLimitException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('String limit reached for your plan.');
    }
}
