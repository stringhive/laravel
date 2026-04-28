<?php

declare(strict_types=1);

namespace Stringhive\Exceptions;

use RuntimeException;

class ValidationException extends RuntimeException
{
    public function __construct(private readonly array $payload)
    {
        parent::__construct($payload['message'] ?? 'Validation failed.');
    }

    public function errors(): array
    {
        return $this->payload['errors'] ?? [];
    }
}
