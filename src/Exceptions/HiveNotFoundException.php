<?php

declare(strict_types=1);

namespace Stringhive\Exceptions;

use RuntimeException;

class HiveNotFoundException extends RuntimeException
{
    public function __construct(string $slug)
    {
        parent::__construct("Hive '{$slug}' not found.");
    }
}
