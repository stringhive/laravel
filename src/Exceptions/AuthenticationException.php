<?php

declare(strict_types=1);

namespace Stringhive\Exceptions;

use RuntimeException;

class AuthenticationException extends RuntimeException
{
    public function __construct(string $message = 'Unauthenticated.')
    {
        parent::__construct($message);
    }
}
