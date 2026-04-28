<?php

declare(strict_types=1);

namespace Stringhive\Enums;

enum ConflictStrategy: string
{
    case Keep = 'keep';
    case Clear = 'clear';
}
